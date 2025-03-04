<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usertags\UsertagCreateRequest;
use App\Http\Requests\Usertags\UsertagUpdateRequest;
use App\Models\Usertag;
use App\Utils\PaginateCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class UsertagController extends Controller
{
    public function index(): View
    {
        $usertags = PaginateCollection::paginate(Usertag::get(), 25);
        return view('usertags.index', ['usertags' => $usertags]);
    }

    public function edit(Usertag $usertag): View
    {
        return view('usertags.edit', ['usertag' => $usertag]);
    }

    public function update(UsertagUpdateRequest $request, Usertag $usertag): RedirectResponse
    {

        if ($request->validated()) {

            $usertag->status = $request->status;
            $usertag->name = $request->name;
            $usertag->color = $request->color;
            $usertag->desc = $request->desc;
            $usertag->save();

            activity('admin')
                ->performedOn($usertag) // kime yapıldı
                ->causedBy(auth()->user()->id) // kim yaptı
                ->event('update') // ne yaptı
                ->withProperties(['title' => 'Kullanıcı Etiketi Güncelleme']) // işlem başlığı
                ->log( __("usertag.activity.update.success", ['authuser' => auth()->user()->name, 'name' => $usertag->name])); // açıklama

            Log::info(
                __("usertag.log.update.success", [
                    'authuser' => auth()->user()->name,
                    'ip' => request()->ip(),
                    'name' => $usertag->name
                ])
            );

            return Redirect::route('panel.user.tags')->with('success', __('usertag.update.success.message'));
        }

        Log::warning(
            __("usertag.log.update.validation.error", [
                'authuser' => auth()->user()->name,
                'ip' => request()->ip(),
                'name' => $usertag->name,
                'error' => $request->validated()->messages()->all()[0]
            ])
        );

        return Redirect::back()->with('error', $request->validated()->messages()->all()[0])->withInput();
    }

    public function store(UsertagCreateRequest $request): JsonResponse
    {

        if ($request->ajax()) {
            if($request->validated()) {
                $usertag = Usertag::create($request->all());

                activity('admin')
                    ->performedOn($usertag) // kime yapıldı
                    ->causedBy(auth()->user()->id) // kim yaptı
                    ->event('create') // ne yaptı
                    ->withProperties(['title' => 'Yeni Kullanıcı Etiketi']) // işlem başlığı
                    ->log( __("usertag.activity.create.success", ['authuser' => auth()->user()->name, 'name' => $usertag->name])); // açıklama

                Log::info(
                    __("usertag.log.create.success", [
                        'authuser' => auth()->user()->name,
                        'ip' => request()->ip(),
                        'name' => $usertag->name
                    ])
                );

                return response()->json(['status' => "success"]);
            }

            Log::warning(
                __("usertag.log.create.validation.error", [
                    'authuser' => auth()->user()->name,
                    'ip' => request()->ip(),
                    'name' => $request->name,
                    'error' => $request->validated()->messages()->all()[0]
                ])
            );

            return response()->json(["status" => "error", "message" => $request->validated()->messages()->all()[0]]);
        }

        Log::warning( __("global.critical.error") );
        return response()->json(['status' => "error", "message" => __("global.critical.error")]);
    }

    public function destroy(Request $request, Usertag $usertag): JsonResponse
    {
        if ($request->ajax()) {

            if ($usertag->users()->count() > 0) {

                Log::warning(
                    __("usertag.log.delete.confirm.error", [
                        'authuser' => auth()->user()->name,
                        'ip' => request()->ip(), 'name' => $request->name
                    ])
                );

                return response()->json([
                    "status" => "error",
                    "message" => __("usertag.log.delete.confirm.error", [
                        'authuser' => auth()->user()->name,
                        'ip' => request()->ip(),
                        'name' => $request->name
                    ])
                ]);
            } else {

                $usertag->delete();

                activity('admin')
                    ->performedOn($usertag) // kime yapıldı
                    ->causedBy(auth()->user()->id) // kim yaptı
                    ->event('delete') // ne yaptı
                    ->withProperties(['title' => 'Kullanıcı Etiketi Silme']) // işlem başlığı
                    ->log(__("activity.delete.success", ['authuser' => auth()->user()->name, 'name' => $usertag->name])); // açıklama

                Log::info(
                    __("activity.delete.success", [
                        'authuser' => auth()->user()->name,
                        'name' => $usertag->name
                    ])
                );

                return response()->json(['status' => "success"]);
            }
        }

        Log::warning( __("global.critical.error") );
        return response()->json(['status' => "error", "message" => __("global.critical.error")]);

    }
}
