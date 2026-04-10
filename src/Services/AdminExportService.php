<?php

declare(strict_types=1);

namespace App\Services;

final class AdminExportService
{
    /**
     * @param array<int, array<string, mixed>> $videos
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function buildCatalogPayload(array $videos, array $filters = []): array
    {
        return [
            'meta' => [
                'generated_at' => gmdate('c'),
                'format' => 'videw-catalog-export',
                'version' => 1,
                'app_name' => (string) config('app.name', 'VIDEW'),
                'app_version' => trim((string) config('updates.current_version', '')),
                'filters' => $filters,
                'count' => count($videos),
            ],
            'videos' => array_map([$this, 'catalogRecord'], $videos),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $users
     * @return array<string, mixed>
     */
    public function buildUsersPayload(array $users, string $search = ''): array
    {
        return [
            'meta' => [
                'generated_at' => gmdate('c'),
                'format' => 'videw-users-export',
                'version' => 1,
                'app_name' => (string) config('app.name', 'VIDEW'),
                'app_version' => trim((string) config('updates.current_version', '')),
                'search' => $search,
                'count' => count($users),
            ],
            'users' => array_map([$this, 'userRecord'], $users),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $videos
     * @return array<int, array<int, string>>
     */
    public function catalogCsvRows(array $videos): array
    {
        $rows = [[
            'id',
            'slug',
            'title',
            'creator_name',
            'creator_user_id',
            'category',
            'access_level',
            'source_type',
            'storage_provider',
            'moderation_status',
            'moderation_reason',
            'is_featured',
            'duration_minutes',
            'published_at',
            'created_at',
            'updated_at',
        ]];

        foreach ($videos as $video) {
            $record = $this->catalogRecord($video);
            $rows[] = [
                (string) $record['id'],
                (string) $record['slug'],
                (string) $record['title'],
                (string) $record['creator_name'],
                (string) $record['creator_user_id'],
                (string) $record['category'],
                (string) $record['access_level'],
                (string) $record['source_type'],
                (string) $record['storage_provider'],
                (string) $record['moderation_status'],
                (string) $record['moderation_reason'],
                (string) ($record['is_featured'] ? '1' : '0'),
                (string) $record['duration_minutes'],
                (string) $record['published_at'],
                (string) $record['created_at'],
                (string) $record['updated_at'],
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $users
     * @return array<int, array<int, string>>
     */
    public function usersCsvRows(array $users): array
    {
        $rows = [[
            'id',
            'display_name',
            'email',
            'role',
            'status',
            'account_tier',
            'stripe_subscription_status',
            'creator_display_name',
            'creator_slug',
            'mfa_enabled',
            'birth_date',
            'adult_confirmed_at',
            'last_login_at',
            'created_at',
        ]];

        foreach ($users as $user) {
            $record = $this->userRecord($user);
            $rows[] = [
                (string) $record['id'],
                (string) $record['display_name'],
                (string) $record['email'],
                (string) $record['role'],
                (string) $record['status'],
                (string) $record['account_tier'],
                (string) $record['stripe_subscription_status'],
                (string) $record['creator_display_name'],
                (string) $record['creator_slug'],
                (string) ($record['mfa_enabled'] ? '1' : '0'),
                (string) $record['birth_date'],
                (string) $record['adult_confirmed_at'],
                (string) $record['last_login_at'],
                (string) $record['created_at'],
            ];
        }

        return $rows;
    }

    public function filename(string $type, string $extension): string
    {
        return 'videw-' . $type . '-' . gmdate('Ymd-His') . '.' . ltrim($extension, '.');
    }

    /**
     * @param array<string, mixed> $video
     * @return array<string, mixed>
     */
    private function catalogRecord(array $video): array
    {
        return [
            'id' => (int) ($video['id'] ?? 0),
            'slug' => (string) ($video['slug'] ?? ''),
            'title' => (string) ($video['title'] ?? ''),
            'synopsis' => (string) ($video['synopsis'] ?? ''),
            'creator_user_id' => (int) ($video['creator_user_id'] ?? 0),
            'creator_name' => (string) ($video['creator_name'] ?? ''),
            'category' => (string) ($video['category'] ?? ''),
            'access_level' => (string) ($video['access_level'] ?? ''),
            'duration_minutes' => (int) ($video['duration_minutes'] ?? 0),
            'source_type' => (string) ($video['source_type'] ?? ''),
            'storage_provider' => (string) ($video['storage_provider'] ?? ''),
            'moderation_status' => (string) ($video['moderation_status'] ?? ''),
            'moderation_reason' => (string) ($video['moderation_reason'] ?? ''),
            'is_featured' => (int) ($video['is_featured'] ?? 0) === 1,
            'video_url' => (string) ($video['video_url'] ?? ''),
            'file_path' => (string) ($video['file_path'] ?? ''),
            'embed_url' => (string) ($video['embed_url'] ?? ''),
            'poster_url' => (string) ($video['poster_url'] ?? ''),
            'poster_path' => (string) ($video['poster_path'] ?? ''),
            'published_at' => (string) ($video['published_at'] ?? ''),
            'created_at' => (string) ($video['created_at'] ?? ''),
            'updated_at' => (string) ($video['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function userRecord(array $user): array
    {
        return [
            'id' => (int) ($user['id'] ?? 0),
            'display_name' => (string) ($user['display_name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'status' => (string) ($user['status'] ?? ''),
            'account_tier' => (string) ($user['account_tier'] ?? ''),
            'stripe_subscription_status' => (string) ($user['stripe_subscription_status'] ?? ''),
            'creator_display_name' => (string) ($user['creator_display_name'] ?? ''),
            'creator_slug' => (string) ($user['creator_slug'] ?? ''),
            'creator_bio' => (string) ($user['creator_bio'] ?? ''),
            'mfa_enabled' => (int) ($user['mfa_enabled'] ?? 0) === 1,
            'birth_date' => (string) ($user['birth_date'] ?? ''),
            'adult_confirmed_at' => (string) ($user['adult_confirmed_at'] ?? ''),
            'last_login_at' => (string) ($user['last_login_at'] ?? ''),
            'created_at' => (string) ($user['created_at'] ?? ''),
        ];
    }
}
