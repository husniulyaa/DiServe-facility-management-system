<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'identity_number' => ['required', 'string', 'max:50'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                'unique:users,email',
                function ($attribute, $value, $fail) {
                    $allowedDomains = [
                        '@students.undip.ac.id',
                        '@lectures.undip.ac.id',
                        '@staff.undip.ac.id',
                        '@officer.undip.ac.id',
                        '@facility.undip.ac.id',
                        '@facillity.undip.ac.id',
                    ];
                    if (str_ends_with(strtolower($value), '@admin.undip.ac.id')) {
                        $fail('Admin tidak dapat melakukan pendaftaran akun melalui halaman ini.');
                        return;
                    }
                    $matches = false;
                    foreach ($allowedDomains as $domain) {
                        if (str_ends_with(strtolower($value), $domain)) {
                            $matches = true;
                            break;
                        }
                    }
                    if (!$matches) {
                        $fail('Gunakan email resmi UNDIP untuk pendaftaran.');
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/[a-z]/', $value)) {
                        $fail('Password harus mengandung huruf kecil.');
                    } elseif (!preg_match('/[A-Z]/', $value)) {
                        $fail('Password harus mengandung huruf besar.');
                    } elseif (!preg_match('/[0-9]/', $value)) {
                        $fail('Password harus mengandung angka.');
                    } elseif (!preg_match('/[@$!%*?&#_]/', $value)) {
                        $fail('Password harus mengandung karakter khusus.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'identity_number.required' => 'NIM/NIP wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ];
    }
}
