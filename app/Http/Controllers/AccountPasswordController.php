<?php

namespace App\Http\Controllers;

use App\Support\Activity;
use Illuminate\Http\Request;

/**
 * Lets any signed-in user change their own password (no Users menu access needed).
 */
class AccountPasswordController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validateWithBag('accountPassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed', 'different:current_password'],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.different' => 'The new password must be different from your current password.',
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $validated['password']])->save();
        $request->session()->regenerate();

        Activity::log($request, 'account_password_changed', $user, [], $user->branch_id ? (int) $user->branch_id : null);

        return back()->with('success', 'Your password has been changed.');
    }
}
