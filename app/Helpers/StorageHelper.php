<?php

namespace App\Helpers;

class StorageHelper
{
    /**
     * Sync a file from storage/app/public to web-accessible storage
     *
     * @param string $filePath - relative path like 'services/covers/filename.jpg'
     * @return bool
     */
    public static function syncToPublic($filePath)
    {
        $source = storage_path('app/public/' . $filePath);
        $destination = base_path('storage/' . $filePath);
        
        // Create destination directory if it doesn't exist
        $destinationDir = dirname($destination);
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }
        
        // Copy the file
        if (file_exists($source)) {
            return copy($source, $destination);
        }
        
        return false;
    }
    
    /**
     * Sync entire directory structure
     *
     * @param string $directory - like 'services'
     * @return int number of files synced
     */
    public static function syncDirectory($directory = '')
    {
        $source = storage_path('app/public/' . $directory);
        $destination = base_path('storage/' . $directory);
        
        if (!is_dir($source)) {
            return 0;
        }
        
        $synced = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $relativePath = substr($file->getRealPath(), strlen($source) + 1);
                $destFile = $destination . '/' . $relativePath;
                $destDir = dirname($destFile);
                
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                
                if (!file_exists($destFile) || filemtime($file) > filemtime($destFile)) {
                    copy($file, $destFile);
                    $synced++;
                }
            }
        }
        
        return $synced;
    }
}