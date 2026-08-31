<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('profile.edit')->with('success', 'تم تحديث بياناتك.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        // The User model casts 'password' => 'hashed', so no manual hashing here.
        $request->user()->update([
            'password' => $request->validated()['password'],
        ]);

        $request->session()->regenerate();

        return redirect()->route('profile.edit')->with('success', 'تم تغيير كلمة المرور.');
    }
}
