<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    // --- UNTUK ADMIN ---
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date'    => 'nullable|date',
            'user_ids'    => 'required|array', // Menerima array ID karyawan
            'user_ids.*'  => 'exists:users,id',
        ]);

        $task = Task::create([
            'title'       => $request->title,
            'description' => $request->description,
            'due_date'    => $request->due_date,
            'created_by'  => Auth::id(),
        ]);

        // Menugaskan tugas ke karyawan yang dipilih
        $task->users()->attach($request->user_ids);

        return response()->json($task, 201);
    }

    // --- UNTUK KARYAWAN ---
    public function myTasks()
    {
        $tasks = Auth::user()->tasks()->latest()->get();
        return response()->json($tasks);
    }

    public function updateTaskStatus(Request $request, Task $task)
    {
        $request->validate(['status' => 'required|in:in_progress,completed']);

        Auth::user()->tasks()->updateExistingPivot($task->id, [
            'status' => $request->status,
        ]);

        return response()->json(['message' => 'Status tugas berhasil diperbarui.']);
    }
}