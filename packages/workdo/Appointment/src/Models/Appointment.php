<?php

namespace Workdo\Appointment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_name',
        'appointment_type',
        'week_day',
        'duration',
        'phone_enabled',
        'question_ids',
        'enabled',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'appointment_type' => 'string',
            'week_day' => 'array',
            'phone_enabled' => 'boolean',
            'question_ids' => 'array',
            'enabled' => 'boolean'
        ];
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function questions()
    {
        return Question::whereIn('id', $this->question_ids ?? []);
    }

    public function getEncryptedId()
    {
        return Crypt::encrypt($this->id);
    }

    public static function findByEncryptedId($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            return static::find($id);
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function defaultdata($company_id = null)
    {
        if (!empty($company_id)) {
            $defaultSettings = [
                'terms_conditions' => json_encode([
                    'content' => '<h2>Terms and Conditions</h2>',
                    'enabled' => true
                ]),
                'privacy_policy' => json_encode([
                    'content' => '<h2>Privacy Policy</h2>',
                    'enabled' => true
                ]),
                'faq_settings' => json_encode([
                    'faq_title' => 'Frequently Asked Questions',
                    'faq_description' => 'Find answers to common questions about our appointment booking system.',
                    'faq_questions' => [
                        ['title' => 'How do I book an appointment?', 'description' => 'You can book an appointment through our online booking system.']
                    ]
                ]),
                'title_text' => 'MeetSpace',
                'footer_text' => '© 2025 WorkDo Dash. All rights reserved.',
            ];

            foreach ($defaultSettings as $key => $value) {
                $existing = AppointmentSetting::where('key', $key)
                    ->where('created_by', $company_id)
                    ->first();

                if (!$existing) {
                    AppointmentSetting::insert([
                        'key' => $key,
                        'value' => $value,
                        'created_by' => $company_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public static function GivePermissionToRoles($role_id = null, $rolename = null)
    {
        $staff_permission = [
            'manage-appointment-dashboard',

            'manage-appointment',

            'manage-schedules',
            'manage-own-schedules',
            'view-schedules',
        ];

        if ($rolename == 'staff') {
            $roles_v = Role::where('name', 'staff')->where('id', $role_id)->first();
            if ($roles_v) {
                foreach ($staff_permission as $permission_v) {
                    $permission = Permission::where('name', $permission_v)->first();
                    if (!empty($permission)) {
                        if (!$roles_v->hasPermissionTo($permission_v)) {
                            $roles_v->givePermissionTo($permission);
                        }
                    }
                }
            }
        }
    }
}