<?php

namespace Agentoom\Core\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Component;

class SecuritySettings extends Component
{
    public bool $showingQrCode = false;

    public bool $showingRecoveryCodes = false;

    public bool $showingConfirmation = false;

    public string $code = '';

    public function enableTwoFactorAuthentication(EnableTwoFactorAuthentication $enable)
    {
        $enable(Auth::user());

        $this->showingQrCode = true;
        $this->showingRecoveryCodes = true;
    }

    public function confirmTwoFactorAuthentication(ConfirmTwoFactorAuthentication $confirm)
    {
        $this->validate([
            'code' => 'required|string',
        ]);

        try {
            $confirm(Auth::user(), $this->code);

            $this->showingQrCode = false;
            $this->showingConfirmation = false;
            $this->code = '';

            $this->dispatch('toast-show', slots: ['text' => 'Two factor authentication confirmed.'], dataset: ['variant' => 'success']);
        } catch (ValidationException $e) {
            $this->addError('code', $e->getMessage());
        }
    }

    public function disableTwoFactorAuthentication(DisableTwoFactorAuthentication $disable)
    {
        $disable(Auth::user());

        $this->showingQrCode = false;
        $this->showingRecoveryCodes = false;
        $this->showingConfirmation = false;

        $this->dispatch('toast-show', slots: ['text' => 'Two factor authentication disabled.'], dataset: ['variant' => 'success']);
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate)
    {
        $generate(Auth::user());

        $this->showingRecoveryCodes = true;

        $this->dispatch('toast-show', slots: ['text' => 'Recovery codes regenerated.'], dataset: ['variant' => 'success']);
    }

    public function deletePasskey($id)
    {
        Auth::user()->passkeys()->findOrFail($id)->delete();

        $this->dispatch('toast-show', slots: ['text' => 'Passkey deleted.'], dataset: ['variant' => 'success']);
    }

    public function passkeyRegistered()
    {
        $this->dispatch('toast-show', slots: ['text' => 'Passkey registered successfully.'], dataset: ['variant' => 'success']);
    }

    public function render()
    {
        $user = Auth::user();

        if ($user) {
            $user->load('passkeys');
        }

        return view('agentoom-core::livewire.admin.security-settings', [
            'user' => $user,
        ])->layout('agentoom-core::layouts.admin');
    }
}
