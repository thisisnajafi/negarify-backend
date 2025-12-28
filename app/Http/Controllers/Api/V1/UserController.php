<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Requests\Api\V1\UploadAvatarRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Get current user profile
     */
    public function show(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'tokens_balance' => (float) $user->tokens_balance,
                'role' => $user->role,
                'is_verified' => $user->is_verified,
                'phone_verified_at' => $user->phone_verified_at?->toISOString(),
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Update user profile
     */
    public function update(UpdateUserRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $validated = $request->validated();

        // Update only provided fields
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
            // Reset email verification if email changed
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
        }

        $user->save();

        Log::info('User profile updated', [
            'user_id' => $user->id,
            'updated_fields' => array_keys($validated),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'tokens_balance' => (float) $user->tokens_balance,
                'role' => $user->role,
                'is_verified' => $user->is_verified,
                'phone_verified_at' => $user->phone_verified_at?->toISOString(),
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Upload user avatar
     */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $file = $request->file('avatar');
        
        // Generate safe filename
        $extension = $file->getClientOriginalExtension();
        $filename = Str::random(40) . '.' . $extension;
        $path = "avatars/{$user->id}/{$filename}";

        // Delete old avatar if exists
        if ($user->avatar_url) {
            $oldPath = $this->extractPathFromUrl($user->avatar_url);
            if ($oldPath && Storage::disk('s3')->exists($oldPath)) {
                Storage::disk('s3')->delete($oldPath);
            }
        }

        // Store new avatar in S3
        $stored = Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()), 'public');

        if (!$stored) {
            Log::error('Avatar upload failed - storage error', [
                'user_id' => $user->id,
                'path' => $path,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload avatar. Please try again later.',
            ], 500);
        }

        // Get public URL
        $avatarUrl = Storage::disk('s3')->url($path);

        // Update user avatar URL
        $user->avatar_url = $avatarUrl;
        $user->save();

        Log::info('Avatar uploaded', [
            'user_id' => $user->id,
            'path' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'data' => [
                'id' => $user->id,
                'avatar_url' => $avatarUrl,
                'updated_at' => $user->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Extract S3 path from full URL
     * 
     * @param string $url Full S3 URL
     * @return string|null Path or null if extraction fails
     */
    private function extractPathFromUrl(string $url): ?string
    {
        // Extract path from URL like: https://bucket.s3.region.amazonaws.com/avatars/1/filename.jpg
        $parsed = parse_url($url);
        
        if (!isset($parsed['path'])) {
            return null;
        }

        // Remove leading slash
        return ltrim($parsed['path'], '/');
    }
}

