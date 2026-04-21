<?php

namespace App\Http\Controllers;

use App\Models\DataSiswa;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $items = DataSiswa::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('nama', 'like', '%'.$q.'%')
                        ->orWhere('nipd', 'like', '%'.$q.'%')
                        ->orWhere('nisn', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('nama')
            ->paginate(20);

        return view('students.index', [
            'title' => 'Data Siswa',
            'q' => $q,
            'items' => $items,
        ]);
    }

    public function show(DataSiswa $student)
    {
        return view('students.show', [
            'title' => $student->nama,
            'student' => $student,
        ]);
    }
}
