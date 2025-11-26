<?php

namespace App\Http\Controllers\PicProduction;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Production;
use App\Models\ProductionResult;
use App\Models\StatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductionController extends Controller
{
    /**
     * GET /api/pic-production/tasks
     * INDEX: Lihat semua production tasks untuk in-house production
     */
    public function index(Request $request)
    {
        try {
            $productions = Production::inHouse()
                ->with([
                    'order' => function ($q) {
                        $q->select('id', 'order_number', 'cust_name', 'product_name', 'product_quantity', 'product_price', 'order_deadline');
                    },
                    'order.statusHistory' => function ($q) {
                        $q->latest('start_time');
                    },
                    'productionResults' => function ($q) {
                        $q->latest('created_at');
                    }
                ])
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'In-House Production Tasks',
                'data' => $productions->map(function ($production) {
                    $latestStatus = $production->order->statusHistory->first();
                    $results = $production->productionResults;

                    return [
                        'production_id' => $production->id,
                        'order_id' => $production->order->id,
                        'order_number' => $production->order->order_number,
                        'customer_name' => $production->order->cust_name,
                        'product_name' => $production->order->product_name,
                        'product_quantity' => $production->order->product_quantity,
                        'product_price' => number_format($production->order->product_price, 0, ',', '.'),
                        'deadline' => $production->order->order_deadline,
                        'production_type' => $production->production_type,
                        'production_status' => $production->production_status,
                        'current_order_status' => $latestStatus?->status_stage ?? 'pending',
                        'total_results' => $results->count(),
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/pic-production/tasks/{orderId}
     * SHOW: Detail order dan production results
     */
    public function show(Request $request, $orderId)
    {
        try {
            $order = Order::with([
                'production.productionResults' => function ($q) {
                    $q->latest('created_at');
                },
                'statusHistory' => function ($q) {
                    $q->latest('start_time');
                },
                'createdBy',
            ])->findOrFail($orderId);

            if (!$order->production) {
                throw new \Exception('Production not found for this order');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer_name' => $order->cust_name,
                        'phone' => $order->cust_phone,
                        'address' => $order->cust_address,
                        'product_name' => $order->product_name,
                        'product_price' => number_format($order->product_price, 0, ',', '.'),
                        'product_quantity' => $order->product_quantity,
                        'order_date' => $order->order_date,
                        'order_deadline' => $order->order_deadline,
                        'order_notes' => $order->order_notes,
                        'created_by' => $order->createdBy?->full_name,
                    ],
                    'production' => [
                        'production_id' => $order->production->id,
                        'production_type' => $order->production->production_type,
                        'production_status' => $order->production->production_status,
                        'production_results' => $order->production->productionResults->map(function ($result) {
                            return [
                                'result_id' => $result->id,
                                'production_id' => $result->production_id,
                                'file_url' => asset('storage/' . $result->production_file),
                                'file_name' => basename($result->production_file),
                                'created_at' => $result->created_at->format('d M Y H:i:s'),
                                'updated_at' => $result->updated_at->format('d M Y H:i:s'),
                            ];
                        }),
                    ],
                    'timeline' => $order->statusHistory->map(function ($history) {
                        return [
                            'history_id' => $history->id,
                            'stage' => $history->status_stage,
                            'updated_by' => $history->updatedBy?->full_name,
                            'start_time' => $history->start_time->format('d M Y H:i:s'),
                            'end_time' => $history->end_time?->format('d M Y H:i:s') ?? null,
                        ];
                    }),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/pic-production/production-results
     * STORE: Upload production result file
     * 
     * Body: form-data
     * {
     *   "production_id": 1,
     *   "production_file": [file]
     * }
     */
    public function storeResult(Request $request)
    {
        $request->validate([
            'production_id' => 'required|integer|exists:productions,id',
            'production_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        DB::beginTransaction();
        try {
            $productionId = (int) $request->input('production_id');

            $production = Production::find($productionId);
            
            if (!$production) {
                throw new \Exception("Production ID {$productionId} not found");
            }

            if ($production->production_status !== 'in_progress') {
                throw new \Exception(
                    "Cannot add result to production with status '{$production->production_status}'. " .
                    "Only 'in_progress' production can accept results."
                );
            }

            $file = $request->file('production_file');
            $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
            $path = $file->storeAs('production', $filename, 'public');

            if (!$path) {
                throw new \Exception('Failed to upload file');
            }

            $result = ProductionResult::create([
                'production_id' => $productionId,
                'production_file' => $path,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Production result uploaded successfully',
                'data' => [
                    'result_id' => $result->id,
                    'production_id' => $result->production_id,
                    'file_name' => basename($result->production_file),
                    'file_url' => asset('storage/' . $result->production_file),
                    'created_at' => $result->created_at->format('d M Y H:i:s'),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/pic-production/production-results/{resultId}
     * DELETE: Hapus production result
     */
    public function destroyResult(Request $request, $resultId)
    {
        DB::beginTransaction();
        try {
            $resultId = (int) $resultId;

            $result = ProductionResult::with('production')->find($resultId);

            if (!$result) {
                throw new \Exception("Production result ID {$resultId} not found");
            }

            if ($result->production->production_status !== 'in_progress') {
                throw new \Exception(
                    "Cannot delete result from production with status '{$result->production->production_status}'"
                );
            }

            $filePath = $result->production_file;
            $result->delete();

            if ($filePath && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Production result deleted successfully',
                'data' => ['deleted_result_id' => $resultId],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/pic-production/productions/{productionId}/complete
     * COMPLETE: Mark production as completed
     * 
     * Admin akan review dan approve/reject hasil production
     */
    public function completeProduction(Request $request, $productionId)
    {
        DB::beginTransaction();
        try {
            $productionId = (int) $productionId;

            $production = Production::find($productionId);

            if (!$production) {
                throw new \Exception("Production ID {$productionId} not found");
            }

            if ($production->production_status !== 'in_progress') {
                throw new \Exception(
                    "Cannot complete production with status '{$production->production_status}'"
                );
            }

            // Check if there are production results
            $resultCount = $production->productionResults()->count();
            if ($resultCount === 0) {
                throw new \Exception('Cannot complete production without any production results');
            }

            $production->production_status = 'completed';
            $production->save();

            DB::commit();
            $production->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Production marked as completed',
                'data' => [
                    'production_id' => $production->id,
                    'production_status' => $production->production_status,
                    'production_type' => $production->production_type,
                    'result_count' => $resultCount,
                    'updated_at' => $production->updated_at->format('d M Y H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
