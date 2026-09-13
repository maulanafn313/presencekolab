<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternGroupController extends Controller
{
    public function index(Request $request)
    {
        $groups = DB::table('intern_groups')->orderBy('created_at', 'desc')->get();
        foreach ($groups as $group) {
            $group->member_count = DB::table('intern_group_members')->where('group_id', $group->id)->count();
            $group->members = DB::table('intern_group_members')
                ->join('users', 'intern_group_members.user_id', '=', 'users.id')
                ->where('intern_group_members.group_id', $group->id)
                ->select('users.id', 'users.nama', 'users.foto_base64')
                ->get();
        }
        return response()->json(['ok' => true, 'data' => $groups]);
    }

    public function store(Request $request)
    {
        $id = $request->input('id');
        $nama = $request->input('nama');
        $mulai = $request->input('tanggal_mulai');
        $selesai = $request->input('tanggal_selesai');

        if ($id) {
            DB::table('intern_groups')->where('id', $id)->update([
                'nama' => $nama,
                'tanggal_mulai' => $mulai,
                'tanggal_selesai' => $selesai,
            ]);
            $msg = 'Kelompok berhasil diupdate.';
        } else {
            DB::table('intern_groups')->insert([
                'nama' => $nama,
                'tanggal_mulai' => $mulai,
                'tanggal_selesai' => $selesai,
            ]);
            $msg = 'Kelompok berhasil dibuat.';
        }
        return response()->json(['ok' => true, 'message' => $msg]);
    }

    public function getMembers(Request $request)
    {
        $groupId = $request->input('group_id');
        $members = DB::table('intern_group_members')
            ->join('users', 'intern_group_members.user_id', '=', 'users.id')
            ->where('intern_group_members.group_id', $groupId)
            ->select('users.id', 'users.nama', 'users.nim', 'users.prodi', 'users.foto_base64')
            ->get();
        return response()->json(['ok' => true, 'data' => $members]);
    }

    public function assignMembers(Request $request)
    {
        $groupId = $request->input('group_id');
        $userIds = $request->input('user_ids', []);

        DB::transaction(function() use ($groupId, $userIds) {
            DB::table('intern_group_members')->where('group_id', $groupId)->delete();
            $inserts = [];
            foreach ($userIds as $uid) {
                $inserts[] = ['group_id' => $groupId, 'user_id' => $uid];
            }
            if (!empty($inserts)) {
                DB::table('intern_group_members')->insert($inserts);
            }
        });

        return response()->json(['ok' => true, 'message' => 'Anggota kelompok berhasil disimpan.']);
    }

    public function archive(Request $request)
    {
        DB::table('intern_groups')->where('id', $request->input('id'))->update(['is_archived' => 1, 'archived_at' => now()]);
        return response()->json(['ok' => true, 'message' => 'Kelompok diarsipkan.']);
    }

    public function unarchive(Request $request)
    {
        DB::table('intern_groups')->where('id', $request->input('id'))->update(['is_archived' => 0]);
        return response()->json(['ok' => true, 'message' => 'Kelompok diaktifkan kembali.']);
    }

    public function destroy(Request $request)
    {
        DB::table('intern_groups')->where('id', $request->input('id'))->delete();
        return response()->json(['ok' => true, 'message' => 'Kelompok berhasil dihapus.']);
    }
}
