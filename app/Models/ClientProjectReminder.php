<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientProjectReminder extends Model
{
    protected $fillable = [
        'project_name',
        'customer_name',
        'whatsapp',
        'email',
        'address',
        'project_type',
        'hosting_provider',
        'active_from',
        'active_until',
        'hosting_login_email',
        'hosting_login_password',
        'payment_status',
        'amount',
        'transaction_date',
        'contact_method',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'hosting_login_email',
        'hosting_login_password',
    ];

    protected function casts(): array
    {
        return [
            'project_name' => 'encrypted',
            'customer_name' => 'encrypted',
            'notes' => 'encrypted',
            'active_from' => 'date',
            'active_until' => 'date',
            'transaction_date' => 'date',
            'whatsapp' => 'encrypted',
            'email' => 'encrypted',
            'address' => 'encrypted',
            'hosting_login_email' => 'encrypted',
            'hosting_login_password' => 'encrypted',
            'amount' => 'integer',
        ];
    }
}
