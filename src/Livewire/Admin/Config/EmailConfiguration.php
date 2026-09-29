<?php

namespace Agentoom\Core\Livewire\Admin\Config;

use Agentoom\Core\Models\Configuration;
use Livewire\Component;

class EmailConfiguration extends Component
{
    public array $gmail = [
        'client_id' => '',
        'client_secret' => '',
        'service_account_json' => '',
        'status' => 'not_connected',
    ];

    public array $smtp = [
        'mail_host' => '',
        'mail_port' => '587',
        'mail_username' => '',
        'mail_password' => '',
        'mail_encryption' => 'tls',
        'mail_from_address' => '',
        'mail_from_name' => '',
    ];

    public array $imap = [
        'mail_imap_host' => '',
        'mail_imap_port' => '993',
        'mail_imap_username' => '',
        'mail_imap_password' => '',
        'mail_imap_encryption' => 'ssl',
    ];

    public function mount(): void
    {
        $this->gmail = array_merge($this->gmail, Configuration::getValue('gmail_integration', []));

        $this->smtp = [
            'mail_host' => Configuration::getValue('mail_host', ''),
            'mail_port' => Configuration::getValue('mail_port', '587'),
            'mail_username' => Configuration::getValue('mail_username', ''),
            'mail_password' => Configuration::getValue('mail_password', ''),
            'mail_encryption' => Configuration::getValue('mail_encryption', 'tls'),
            'mail_from_address' => Configuration::getValue('mail_from_address', ''),
            'mail_from_name' => Configuration::getValue('mail_from_name', ''),
        ];

        $this->imap = [
            'mail_imap_host' => Configuration::getValue('mail_imap_host', ''),
            'mail_imap_port' => Configuration::getValue('mail_imap_port', '993'),
            'mail_imap_username' => Configuration::getValue('mail_imap_username', ''),
            'mail_imap_password' => Configuration::getValue('mail_imap_password', ''),
            'mail_imap_encryption' => Configuration::getValue('mail_imap_encryption', 'ssl'),
        ];
    }

    public function save(string $service): void
    {
        $data = $this->{$service};

        if ($service === 'smtp' || $service === 'imap') {
            foreach ($data as $key => $value) {
                Configuration::setValue($key, $value);
            }
        } else {
            Configuration::setValue("{$service}_integration", $data);
        }

        session()->flash("{$service}_success", __('agentoom-core::admin.integrations.credentials_saved'));
    }

    public function connect(string $service): mixed
    {
        $this->save($service);

        return redirect()->route('admin.oauth.redirect', ['service' => $service]);
    }

    public function disconnect(string $service): void
    {
        $data = $this->{$service};
        $data['access_token'] = null;
        $data['refresh_token'] = null;
        $data['status'] = 'not_connected';
        $data['authorized_email'] = null;
        $data['expires_at'] = null;

        $this->{$service} = $data;
        Configuration::setValue("{$service}_integration", $data);
    }

    public function getRedirectUri(string $service): string
    {
        $driver = match ($service) {
            'gmail' => 'google',
            default => $service,
        };

        return route('admin.oauth.callback', ['service' => $driver]);
    }

    public function render(): mixed
    {
        return view('agentoom-core::livewire.admin.config.email-configuration')
            ->layout('agentoom-core::layouts.admin', [
                'title' => __('agentoom-core::admin.integrations.title'),
                'headerTitle' => __('agentoom-core::admin.integrations.title'),
            ]);
    }
}
