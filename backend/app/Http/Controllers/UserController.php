<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }

        $file = $request->file('avatar');
        $filename = $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('public/avatar', $filename);

        // Supprimer l'ancien avatar si ce n'est pas user.jpg
        if ($user->user_profile_picture && $user->user_profile_picture !== 'user.jpg') {
            Storage::delete('public/avatar/' . $user->user_profile_picture);
        }

        $user->user_profile_picture = $filename;
        $user->save();

        return response()->json([
            'success' => true,
            'avatar_url' => asset('storage/avatar/' . $filename)
        ]);
    }
}

