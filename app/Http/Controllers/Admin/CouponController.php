<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Coupons\Actions\CreateCoupon;
use App\Domain\Coupons\Actions\DeleteCoupon;
use App\Domain\Coupons\Actions\UpdateCoupon;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(): Response
    {
        $coupons = Coupon::query()
            ->withCount('usages')
            ->orderByDesc('created_at')
            ->paginate(15);

        return Inertia::render('admin/coupons/index', [
            // Resource collection on purpose — the shared <Pagination>
            // needs the data/meta/links shape (landmine: /admin/providers).
            'coupons' => CouponResource::collection($coupons),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/coupons/create');
    }

    public function store(StoreCouponRequest $request, CreateCoupon $action): RedirectResponse
    {
        $action->handle($request->validated());

        return to_route('admin.coupons.index')->with('success', __('Coupon created.'));
    }

    public function edit(Coupon $coupon): Response
    {
        return Inertia::render('admin/coupons/edit', [
            'coupon' => new CouponResource($coupon->loadCount('usages')),
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon, UpdateCoupon $action): RedirectResponse
    {
        $action->handle($coupon, $request->validated());

        return to_route('admin.coupons.index')->with('success', __('Coupon updated.'));
    }

    public function destroy(Coupon $coupon, DeleteCoupon $action): RedirectResponse
    {
        $action->handle($coupon);

        return to_route('admin.coupons.index')->with('success', __('Coupon deleted.'));
    }
}
