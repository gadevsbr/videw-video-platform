<?php

declare(strict_types=1);

namespace App\Storage;

interface StorageDriverInterface
{
    /**
     * @return array{disk:string,path:string,url:string,mime_type:string,size:int}
     */
    public function storeUploadedFile(array $file, string $directory): array;

    public function delete(string $path): void;
}
