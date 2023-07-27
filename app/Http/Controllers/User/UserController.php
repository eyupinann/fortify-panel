<?php

namespace App\Http\Controllers\User;

use App\Enums\Status;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Usertag;
use App\Utils\PaginateCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Kullanıcıları listeleme sayfası
     * Durumu silinmiş olmayanlar dışında kalanlar
     * ve türü User olanlar
     */
    public function index(): View
    {
        $roles = Role::where('type', UserType::USER)->get();
        $users = User::whereNotIn('status', [UserStatus::DELETED])->where('type', [UserType::USER])->get();
        $users = PaginateCollection::paginate($users, 25);
        $tags = Usertag::where('status', [Status::ACTIVE])->has('users')->get();

        return view('users.index', [
            'users' => $users,
            'roles' => $roles,
            'tags' => $tags
        ]);
    }

    /**
     * Kullanıcı bilgilerini modala alma
     *
     * @param  array<string, string>  $input
     */
    public function userModelData(Request $request): JsonResponse
    {
        if ($request->ajax() && $request->has('ids')) {
            $user = User::where('id', $request->ids)->with('roles')->get();
            return response()->json([
                'user_data' => $user
            ]);
        }
    }

    /**
     * Kullanıcı rolünü güncelleme
     * Ek rol tanımlama
     *
     * @param  array<string, string>  $input
     */
    public function updateRole(Request $request): RedirectResponse
    {
        $user = User::findOrFail($request->user);
        if(is_array($request->role)) {
            foreach ($request->role as $role) {
                $user->assignRole([$role]);
            }
            return Redirect::route('panel.users')->with('success', 'Kullanıcı yetkileri başarılı bir şekilde güncellendi');
        }

        return Redirect::route('panel.users')->with('error', 'Lütfen yetki seçiniz');
    }

    /**
     * Kullanıcıları filtreleme
     *
     * Kullanıcılar sayfasındaki ajax filtreleme
     * sonucunun döndüğü kısım
     */
    public function filter(Request $request)
    {
        $users = User::query()->where('type', [UserType::USER]);

        if ($request->has('searchText') && $request->searchText != null) {
            $users->where('name', 'LIKE', "%{$request->searchText}%");
        }

        if ($request->has('statusIds') && $request->statusIds != null) {
            $users->whereIn('status', $request->statusIds);
        }

        if ($request->has('tagIds') && $request->tagIds != null) {
            $users->whereHas('usertags', function ($query) use ($request) {
                $query->whereIn('usertag_id', $request->tagIds);
            });
        }

        if (!$request->has('statusIds') && !$request->has('tagIds') && !$request->has('searchText') && $request->has('page')) {
            $users->paginate('25', ['*'], 'page', $request->page);
            $users = $users->getCollection();
        } else {
            $users = $users->get();
        }

        $users->each(function ($user) {
            $user->roleName = $user->roles->pluck('name')->first();
        });

        return \response()->json($users);
    }

    /**
     * Kullanıcılar sayfasındaki arama formu
     */
    public function search(Request $request)
    {
        if($request->ajax())
        {
            $output = '';
            $roles = '';
            $query = $request->get('query');
            if($query != '')
            {
                $data = User::select('id', 'type', 'status', 'name', 'email')
                    ->where('type', UserType::USER)
                    ->where("name", "LIKE", "%{$query}%")
                    ->oRwhere("email", "LIKE", "%{$query}%")
                    ->get('query');
            }

            $total_row = $data->count();
            if($total_row > 0)
            {
                foreach($data as $row)
                {
                    foreach($row->getRoleNames() as $role) {
                        $roles .= '<li><span class="fw-semibold mr-2 mb-2">'.$role.'</span></li>';
                    }

                    $output .= '
                        <tr>
                            <td>
                                <span class="badge fw-normal '.UserStatus::color($row->status).'">'.UserStatus::title($row->status).'</span>
                            </td>
                            <td>'.$row->name.'</td>
                            <td>'.$row->email.'</td>
                            <td>
                                <ul class="list-unstyled list-inline m-0 p-0">'.$roles.'</ul>
                            </td>
                            <td class="text-center">
                            <div class="dropdown">
                                <a class="btn btn-text dropdown-toggle p-0" href="#"
                                    role="button" data-bs-toggle="dropdown" data-boundary="window"
                                    aria-haspopup="true" aria-expanded="false">
                                    <i class="ri-menu-3-fill"></i>
                                </a>
                                <ul
                                    class="dropdown-menu dropdown-menu-end rounded-0 shadow-none bg-white">
                                    <li><a class="dropdown-item small"
                                            href="'.route('panel.user.detail', $row->id).'">Bilgiler</a>
                                    </li>
                                    <li class="dropdown-divider"></li>
                                    <li>
                                        <button id="addRole" type="button"
                                            class="btn btn-text btn-sm dropdown-item"
                                            value="'.$row->id.'" data-bs-toggle="modal"
                                            data-bs-target="#changeRole">Rol Tanımla</button>
                                    </li>
                                    <li><a class="dropdown-item small"
                                            href="'.route('panel.user.permissions', $row->id).'">Özel
                                            Yetkiler</a>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>';
                }
            } else {
                $output = '<tr><td align="center" colspan="5">Bu isim veya e-mail adresi ile kayıtlı kullanıcı bulunmamaktadır</td></tr>';
            }
            $data = array(
                'table_data'  => $output,
                'total_data'  => $total_row
            );

            echo json_encode($data);
        }
    }

    public function tags(Usertag $usertag)
    {
        //dd($usertag);

        $users = Usertag::find($usertag)->users()->get();
        $users = PaginateCollection::paginate($users, 25);

        return view('users.tags', ['users' => $users]);

    }
}
