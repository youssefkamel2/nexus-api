<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait SecureIdTrait
{
    /**
     * Encode the database ID for public use
     *
     * @param int $id
     * @return string
     */
    public static function encodeId($id)
    {
        return base64_encode(Crypt::encryptString($id . '_nexus_' . config('app.key')));
    }

    /**
     * Decode the encoded ID to get database ID
     *
     * @param string $encodedId
     * @return int|null
     */
    public static function decodeId($encodedId)
    {
        try {
            $decrypted = Crypt::decryptString(base64_decode($encodedId));
            $parts = explode('_nexus_', $decrypted);
            
            if (count($parts) === 2 && $parts[1] === config('app.key')) {
                return (int) $parts[0];
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the encoded ID for this model instance
     *
     * @return string
     */
    public function getEncodedIdAttribute()
    {
        return self::encodeId($this->id);
    }

    /**
     * Find model by encoded ID
     *
     * @param string $encodedId
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public static function findByEncodedId($encodedId)
    {
        $realId = self::decodeId($encodedId);
        
        if ($realId) {
            return self::find($realId);
        }
        
        return null;
    }

    /**
     * Find model by encoded ID or fail
     *
     * @param string $encodedId
     * @return \Illuminate\Database\Eloquent\Model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findByEncodedIdOrFail($encodedId)
    {
        $model = self::findByEncodedId($encodedId);
        
        if (!$model) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
        }
        
        return $model;
    }
}
