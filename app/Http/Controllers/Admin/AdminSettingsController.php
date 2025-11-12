<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Mail\VerificationCodeMail;
use App\Traits\ResponseTrait;
use App\Helpers\StorageHelper;

class AdminSettingsController extends Controller
{
    use ResponseTrait;
    /**
     * Request to update profile: just send a verification code to current email.
     */
    public function requestUpdate(Request $request): JsonResponse
    {
        // get authenticated user
        $user = Auth::user();
        $code = random_int(100000, 999999);
        $expiresAt = now()->addMinutes(15);

        $user->email_verification_code = $code;
        $user->email_verification_expires_at = $expiresAt;
        $user->save();

        // Send code to current email using Mailable
        Mail::to($user->email)->send(new VerificationCodeMail($code));

        return $this->success(null, 'Verification code sent to your email.');
    }

    /**
     * Confirm update: receive code and update data, validate code, and apply update if code is correct.
     */
    public function confirmUpdate(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8',
            'bio' => 'sometimes|string',
            'profile_image' => 'sometimes|file|image|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        if (
            !$user->email_verification_code ||
            $user->email_verification_code != $request->code ||
            !$user->email_verification_expires_at ||
            now()->gt($user->email_verification_expires_at)
        ) {
            Log::warning('Invalid verification code attempt', ['user_id' => $user->id]);
            return $this->error('Verification failed', 403);
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }
        if ($request->hasFile('profile_image')) {
            $path = $request->file('profile_image')->store('profile_images', 'public');
            StorageHelper::syncToPublic($path);
            $user->profile_image = $path;
        }
        if ($request->has('bio')) {
            $user->bio = $request->bio;
        }
        $user->email_verification_code = null;
        $user->email_verification_expires_at = null;
        $user->save();

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'bio' => $user->bio,
            'profile_image' => $user->profile_image ? env('APP_URL') . '/storage/' . $user->profile_image : null,
            'status' => $user->status,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ], 'Profile updated successfully.');
    }
}
