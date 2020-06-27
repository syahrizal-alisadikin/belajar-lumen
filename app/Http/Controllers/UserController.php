<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\User;

class UserController extends Controller
{
    //METHOD UNTUK RESTFUL API AKAN DITEMPATKAN DISINI

    public function index()
    {
        // Query untuk mengambil data dari tabel users dan di load 10 data perhalaman
        $user = User::orderBy('created_at', 'desc')->paginate(10);
        // Kembalikan response berupa json dengan format
        // status = Success
        // data = data user berhasil diambil
        return response()->json(['status' => 'data berhasil diambil', 'data' => $user]);
    }

    public function store(Request $request)
    {
        // Validasi
        $this->validate($request, [
            'name' => 'required|string|max:50',
            'identity_id' => 'required|string|unique:users', //UNIQUE BERARTI DATA INI TIDAK BOLEH SAMA DI DALAM TABLE USERS
            'gender' => 'required',
            'address' => 'required|string',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'phone_number' => 'required|string',
            'role' => 'required',
            'status' => 'required',
        ]);

        // Defaul filename adalah nul karena user bkan driver jadi bisa kosong
        $filename = null;
        // Kemudian cek jika ada file yang di kirimkan
        if ($request->hasFile('photo')) {
            // maka generate nama untuk file tersebut
            $filename = Str::random(5) . $request->email . '.jpg';
            $file = $request->file('photo');
            // Simpan file tersebut ke folder public / image
            $file->move(base_path('public/image'), $filename);

            // Simpan data user ke dalam table users menggunakan model
            User::create([
                'name' => $request->name,
                'identity_id' => $request->identity_id,
                'gender' => $request->gender,
                'address' => $request->address,
                'photo' => $filename,
                'email' => $request->email,
                'password' => app('hash')->make($request->password),
                'phone_number' => $request->phone_number,
                'api_token' => Str::random(40),
                'role' => $request->role,
                'status' => $request->status

            ]);
            return response()->json(['status' => 'success ditambah']);
        }
    }

    public function edit($id)
    {
        // Ngambil Data berdasarkan id
        $user = User::findOrFail($id);
        // Kemudian kirim datanya dalam bentuk json
        return response()->json(['status' => 'Edit data', 'data' => $user]);
    }

    public function update(Request $request, $id)
    {
        // Validasi
        $this->validate($request, [
            'name' => 'required|string|max:50',
            'identity_id' => 'required|string|unique:users,identity_id,' . $id, //VALIDASI INI BERARTI ID YANG INGIN DIUPDATE AKAN DIKECUALIKAN UNTUK FILTER DATA UNIK
            'gender' => 'required',
            'address' => 'required|string',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png', //MIMES BERARTI KITA HANYA MENGIZINKAN EXTENSION FILE YANG DISEBUTKAN
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'required|min:6',
            'phone_number' => 'required|string',
            'role' => 'required',
            'status' => 'required',
        ]);
        $user = User::findOrFail($id); //Get data user berdasarkan Id
        // Jia password kosong brarti user tidak ingin mengganti password
        // jika tidak kosong, maka kita encrypt password baru
        $password = $request->password != '' ? app('hash')->make($request->password) : $user->password;

        //   Logic yang sama adalah default dari $filename adalah nama file dari database
        $filename = $user->photo;
        // jika ada gambar yang dikirim
        if ($request->hasFile('photo')) {
            // maka kita generate nama dan simpan file baru tersebut
            $filename = Str::random(5) . $user->email . '.jpg';
            $file = $request->file('photo');
            $file->move(base_path('public/image'), $filename);
            //Hapus file Lama
            unlink(base_path('public/image/' . $user->photo));
        }

        // Kemudian perbaharui data Users
        $user->update([
            'name' => $request->name,
            'identify_id' => $request->identify_id,
            'gender' => $request->gender,
            'address' => $request->address,
            'photo' => $filename,
            'password' => $password,
            'phone_number' => $request->phone_number,
            'role' => $request->role,
            'status' => $request->status

        ]);
        return response()->json(['status' => 'Data Berhasil diupdate']);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        unlink(base_path('public/image/' . $user->photo));
        $user->delete();

        return response()->json(['status' => 'success']);
    }

    public function login(Request $request)
    {
        // Validasi input User
        // Dengan Ketentuan Email Harus ada di table User dan password min 6
        $this->validate($request, [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:6'
        ]);

        // kita Cari User Berdasarkan email
        $user = User::where('email', $request->email)->first();
        // Jika Data User Ada
        // Kita Check password user apakah sudah sesuai atau belum
        // Untuk Meembandikan encryted password dengan plain text kita bisa menggunakan facades check
        if ($user && Hash::check($request->password, $user->password)) {
            $token = Str::random(40); //Generate Token baru
            $user->update(['api_token' => $token]); //Update User Terkait
            // Dan Kembalikan Token Untuk digunakan pada client
            return response()->json(['status' => 'Success Login', 'data' => $token]);
        }

        // Jika tidak sesuai berikan response error
        return response()->json(['status' => 'Gagal Login']);
    }
}
