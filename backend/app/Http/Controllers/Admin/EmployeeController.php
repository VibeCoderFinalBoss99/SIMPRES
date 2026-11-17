<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $users = User::with('workLocation')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('nip', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.employees.index', ['users' => $users, 'search' => $search]);
    }

    public function create(): View
    {
        return view('admin.employees.form', [
            'user' => new User(['role' => 'pegawai', 'work_location_id' => WorkLocation::value('id')]),
            'locations' => WorkLocation::orderBy('name')->get(),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        // Password otomatis disimpan sebagai hash oleh cast 'hashed' di model User.
        $user = User::create($request->validated());

        return redirect()->route('admin.pegawai.index')->with('success', "Pegawai {$user->name} berhasil ditambahkan.");
    }

    public function edit(User $user): View
    {
        return view('admin.employees.form', [
            'user' => $user,
            'locations' => WorkLocation::orderBy('name')->get(),
        ]);
    }

    public function update(EmployeeRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']); // password kosong = tidak diganti
        }

        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return back()->withInput()->withErrors(['role' => 'Anda tidak bisa mengubah peran akun Anda sendiri.']);
        }

        $user->update($data);

        return redirect()->route('admin.pegawai.index')->with('success', "Data {$user->name} berhasil disimpan.");
    }

    /** Aktifkan atau nonaktifkan akun. Akun nonaktif tidak bisa login dan tokennya dihapus. */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['is_active' => 'Anda tidak bisa menonaktifkan akun Anda sendiri.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Akun {$user->name} {$status}.");
    }
}
