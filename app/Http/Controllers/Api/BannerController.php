<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBannerRequest;
use App\Http\Requests\UpdateBannerRequest;
use App\Http\Resources\BannerResource;
use App\Services\BannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Banners
 * APIs for banners
 */
class BannerController extends Controller
{
    public function __construct(private BannerService $service)
    {
    }

    /**
     * List banners
     *
     * @OA\Get(
     *     path="/api/banners",
     *     summary="List banners",
    *     tags={"Banners"},
    *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="valid", in="query", @OA\Schema(type="integer", enum={0,1})),
     *     @OA\Response(
     *         response=200,
     *         description="A paginated list of banners",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Banner")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $paginator = $this->service->index($request->only(['per_page', 'status', 'valid']));

        return BannerResource::collection($paginator->appends($request->query()));
    }

    /**
     * Store a new banner
     *
     * @OA\Post(
     *     path="/api/banners",
     *     summary="Create a banner",
    *     tags={"Banners"},
    *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="media", type="string", format="binary"),
     *                 @OA\Property(property="image_url", type="string"),
     *                 @OA\Property(property="status", type="string", enum={"draft","published","archived"}),
     *                 @OA\Property(property="start_at", type="string", format="date-time"),
     *                 @OA\Property(property="end_at", type="string", format="date-time"),
     *                 required={"title","status"}
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Banner created", @OA\JsonContent(ref="#/components/schemas/Banner")),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreBannerRequest $request): JsonResponse
    {
        $banner = $this->service->store($request->validated() + $request->only('media'));

        return (new BannerResource($banner))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a banner
     *
     * @OA\Get(
     *     path="/api/banners/{id}",
     *     summary="Get a banner",
    *     tags={"Banners"},
    *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Banner found", @OA\JsonContent(ref="#/components/schemas/Banner")),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function show($id)
    {
        $banner = $this->service->show((int) $id);
        if (!$banner) {
            return response()->json(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        return new BannerResource($banner);
    }

    /**
     * Update a banner
     *
     * @OA\Put(
     *     path="/api/banners/{id}",
     *     summary="Update a banner",
    *     tags={"Banners"},
    *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="media", type="string", format="binary"),
     *                 @OA\Property(property="image_url", type="string"),
     *                 @OA\Property(property="status", type="string", enum={"draft","published","archived"}),
     *                 @OA\Property(property="start_at", type="string", format="date-time"),
     *                 @OA\Property(property="end_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/Banner")),
     *     @OA\Response(response=404, description="Not Found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateBannerRequest $request, $id)
    {
        $banner = $this->service->update((int) $id, $request->validated() + $request->only('media'));

        if (!$banner) {
            return response()->json(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        return new BannerResource($banner);
    }

    /**
     * Delete a banner
     *
     * @OA\Delete(
     *     path="/api/banners/{id}",
     *     summary="Delete a banner",
    *     tags={"Banners"},
    *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=404, description="Not Found")
     * )
     */
    public function destroy($id): JsonResponse
    {
        $deleted = $this->service->delete((int) $id);
        if (!$deleted) {
            return response()->json(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
