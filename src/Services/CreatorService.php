<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CreatorApplicationRepository;
use App\Repositories\UserRepository;
use App\Repositories\VideoRepository;
use RuntimeException;

final class CreatorService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly CreatorApplicationRepository $applications = new CreatorApplicationRepository(),
        private readonly StorageManager $storage = new StorageManager(),
        private readonly VideoSourceService $sources = new VideoSourceService(),
        private readonly MediaCleanupService $cleanup = new MediaCleanupService(),
        private readonly VideoRepository $videos = new VideoRepository()
    ) {
    }

    /**
     * @param array<string, string> $input
     * @return array{success:bool,message:string,application_id?:int}
     */
    public function submitApplication(int $userId, array $input): array
    {
        $user = $this->users->findById($userId);

        if (!$user) {
            return ['success' => false, 'message' => 'Account not found.'];
        }

        if ((string) ($user['role'] ?? '') === 'creator') {
            return ['success' => false, 'message' => 'This account already has creator access.'];
        }

        $channelName = trim((string) ($input['requested_display_name'] ?? creator_public_name($user)));
        $desiredSlug = trim((string) ($input['requested_slug'] ?? ''));
        $about = trim((string) ($input['requested_bio'] ?? ''));

        if ($channelName === '') {
            return ['success' => false, 'message' => 'Channel name is required.'];
        }

        $resolvedSlug = $this->users->generateUniqueCreatorSlug($desiredSlug !== '' ? $desiredSlug : $channelName, $userId);

        try {
            $applicationId = $this->applications->createOrRefreshPending($userId, [
                'requested_display_name' => $channelName,
                'requested_slug' => $resolvedSlug,
                'requested_bio' => $about,
            ]);
        } catch (RuntimeException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        return [
            'success' => true,
            'message' => 'Creator request sent. We will review it soon.',
            'application_id' => $applicationId,
        ];
    }

    /**
     * @param array<string, string> $input
     * @param array<string, array<string, mixed>> $files
     * @return array{success:bool,message:string}
     */
    public function updateProfile(int $userId, array $input, array $files): array
    {
        $user = $this->users->findById($userId);

        if (!$user) {
            return ['success' => false, 'message' => 'Creator account not found.'];
        }

        $this->assertUploadStatus($files['avatar_file'] ?? null, 'avatar');
        $this->assertUploadStatus($files['banner_file'] ?? null, 'banner');

        $channelName = trim((string) ($input['creator_display_name'] ?? creator_public_name($user)));
        $bio = trim((string) ($input['creator_bio'] ?? (string) ($user['creator_bio'] ?? '')));
        $slugInput = trim((string) ($input['creator_slug'] ?? (string) ($user['creator_slug'] ?? '')));
        $slug = $this->users->generateUniqueCreatorSlug($slugInput !== '' ? $slugInput : $channelName, $userId);

        if ($channelName === '') {
            return ['success' => false, 'message' => 'Channel name is required.'];
        }

        $payload = [
            'creator_display_name' => $channelName,
            'creator_slug' => $slug,
            'creator_bio' => $bio !== '' ? $bio : null,
            'creator_avatar_url' => $user['creator_avatar_url'] ?? null,
            'creator_avatar_path' => $user['creator_avatar_path'] ?? null,
            'creator_avatar_storage_provider' => $user['creator_avatar_storage_provider'] ?? null,
            'creator_banner_url' => $user['creator_banner_url'] ?? null,
            'creator_banner_path' => $user['creator_banner_path'] ?? null,
            'creator_banner_storage_provider' => $user['creator_banner_storage_provider'] ?? null,
        ];

        try {
            $this->applyImageUpdate('avatar', $payload, $user, $input, $files);
            $this->applyImageUpdate('banner', $payload, $user, $input, $files);
            $this->users->updateCreatorProfile($userId, $payload);
            $this->videos->syncCreatorIdentity($userId, $channelName);
        } catch (RuntimeException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }

        return [
            'success' => true,
            'message' => 'Creator profile updated.',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $user
     * @param array<string, string> $input
     * @param array<string, array<string, mixed>> $files
     */
    private function applyImageUpdate(string $type, array &$payload, array $user, array $input, array $files): void
    {
        $sourceKey = $type . '_source_mode';
        $urlKey = $type . '_external_url';
        $fileKey = $type . '_file';
        $removeKey = 'remove_' . $type;
        $fieldPrefix = 'creator_' . $type;
        $sourceMode = trim((string) ($input[$sourceKey] ?? ''));
        $removeCurrent = ($input[$removeKey] ?? '') === '1';

        if (!in_array($sourceMode, ['', 'upload', 'url'], true)) {
            throw new RuntimeException('Choose how you want to update the ' . $type . ' image.');
        }

        if ($removeCurrent && !empty($user[$fieldPrefix . '_path'])) {
            $this->cleanup->removePath(
                (string) ($user[$fieldPrefix . '_storage_provider'] ?? ''),
                (string) $user[$fieldPrefix . '_path']
            );
            $payload[$fieldPrefix . '_url'] = null;
            $payload[$fieldPrefix . '_path'] = null;
            $payload[$fieldPrefix . '_storage_provider'] = null;
        }

        if ($sourceMode === 'url') {
            $url = trim((string) ($input[$urlKey] ?? ''));

            if ($url === '') {
                throw new RuntimeException('Enter a valid ' . $type . ' URL.');
            }

            $resolvedUrl = $this->sources->resolvePosterUrl($url);

            if (!empty($user[$fieldPrefix . '_path'])) {
                $this->cleanup->removePath(
                    (string) ($user[$fieldPrefix . '_storage_provider'] ?? ''),
                    (string) $user[$fieldPrefix . '_path']
                );
            }

            $payload[$fieldPrefix . '_url'] = $resolvedUrl;
            $payload[$fieldPrefix . '_path'] = null;
            $payload[$fieldPrefix . '_storage_provider'] = 'external';

            return;
        }

        if ($sourceMode === 'upload' && isset($files[$fileKey]) && uploaded_file_present($files[$fileKey])) {
            $upload = $this->storage->driver()->storeUploadedFile($files[$fileKey], 'posters');

            if (!empty($user[$fieldPrefix . '_path'])) {
                $this->cleanup->removePath(
                    (string) ($user[$fieldPrefix . '_storage_provider'] ?? ''),
                    (string) $user[$fieldPrefix . '_path']
                );
            }

            $payload[$fieldPrefix . '_url'] = $upload['url'];
            $payload[$fieldPrefix . '_path'] = $upload['path'];
            $payload[$fieldPrefix . '_storage_provider'] = $upload['disk'];
        }
    }

    /**
     * @param array<string, mixed>|null $file
     */
    private function assertUploadStatus(?array $file, string $label): void
    {
        if (!is_array($file) || !isset($file['error'])) {
            return;
        }

        $error = (int) $file['error'];

        if ($error === UPLOAD_ERR_OK || $error === UPLOAD_ERR_NO_FILE) {
            return;
        }

        $prefix = ucfirst($label) . ' upload failed';

        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $prefix . '. The file is larger than the server limit.',
            UPLOAD_ERR_PARTIAL => $prefix . '. The upload was interrupted before it finished.',
            UPLOAD_ERR_NO_TMP_DIR => $prefix . '. The server is missing a temporary upload folder.',
            UPLOAD_ERR_CANT_WRITE => $prefix . '. The server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION => $prefix . '. A server extension blocked the upload.',
            default => $prefix . '. Try again.',
        };

        throw new RuntimeException($message);
    }
}
