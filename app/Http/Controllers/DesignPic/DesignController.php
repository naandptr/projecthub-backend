<?php

namespace App\Http\Controllers\DesignPic;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Design;
use App\Models\DesignItem;
use App\Models\StatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DesignController extends Controller
{
    /**
     * GET /api/designer/tasks
     * INDEX: Get all my tasks (orders assigned to me)
     */
    public function index(Request $request)
    {
        try {
            $userId = auth()->user()->id;

            $designs = Design::where('assigned_to', $userId)
                ->with([
                    'order.createdBy',
                    'order.statusHistory' => function ($query) {
                        $query->latest('start_time');
                    },
                    'designItems' => function ($query) {
                        $query->latest('created_at');
                    }
                ])
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'My design tasks',
                'data' => $designs->map(function ($design) {
                    $latestStatus = $design->order->statusHistory->first();
                    $items = $design->designItems;

                    return [
                        'id' => $design->id,
                        'order_id' => $design->order->id,
                        'order_number' => $design->order->order_number,
                        'customer_name' => $design->order->cust_name,
                        'product_name' => $design->order->product_name,
                        'product_quantity' => $design->order->product_quantity,
                        'product_price' => number_format($design->order->product_price, 0, ',', '.'),
                        'deadline' => $design->order->order_deadline,
                        'current_status' => $latestStatus?->status_stage ?? 'pending',
                        'design_stats' => [
                            'in_progress' => $items->where('design_status', 'in_progress')->count(),
                            'approved' => $items->where('design_status', 'approved')->count(),
                            'revision' => $items->where('design_status', 'revision')->count(),
                            'total' => $items->count(),
                        ],
                        'progress_percentage' => $items->count() > 0 
                            ? round((($items->where('design_status', 'approved')->count() / $items->count()) * 100), 0) 
                            : 0,
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
     * GET /api/designer/tasks/{orderId}
     * SHOW: Get detail order dan design items
     */
    public function show(Request $request, $orderId)
    {
        try {
            $userId = auth()->user()->id;

            $order = Order::with([
                'design.designItems' => function ($query) {
                    $query->latest('created_at');
                },
                'design.assignedTo',
                'statusHistory' => function ($query) {
                    $query->latest('start_time');
                },
                'createdBy',
            ])->findOrFail($orderId);

            // Verify PIC Design ini punya hak akses
            if ($order->design->assigned_to !== $userId) {
                throw new \Exception('You do not have permission to view this order');
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
                        'created_at' => $order->created_at->format('d M Y H:i:s'),
                    ],
                    'design' => [
                        'id' => $order->design->id,
                        'assigned_to' => $order->design->assignedTo?->full_name,
                        'assigned_to_id' => $order->design->assigned_to,
                        'design_items' => $order->design->designItems->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'design_id' => $item->design_id,
                                'file_url' => asset('storage/' . $item->design_file),
                                'file_name' => basename($item->design_file),
                                'notes' => $item->design_notes,
                                'status' => $item->design_status,
                                'created_at' => $item->created_at->format('d M Y H:i:s'),
                                'updated_at' => $item->updated_at->format('d M Y H:i:s'),
                            ];
                        }),
                    ],
                    'timeline' => $order->statusHistory->map(function ($history) {
                        return [
                            'id' => $history->id,
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

public function start(Request $request, $orderId)
{
    DB::beginTransaction();
    try {
        $userId = auth()->user()->id;

        // Cek order ada
        $order = Order::with('design')->findOrFail($orderId);

        if (!$order->design) {
            throw new \Exception('Design not found for this order');
        }

        // ✅ FIX: Jika sudah di-assign ke saya, boleh continue
        // Jika di-assign ke orang lain, throw error
        if ($order->design->assigned_to !== null && $order->design->assigned_to !== $userId) {
            throw new \Exception('This order already assigned to another designer');
        }

        // Jika sudah di-assign ke saya, return success
        if ($order->design->assigned_to === $userId) {

        // Cek apakah sudah ada status designing
        $hasDesigning = $order->statusHistory()
            ->where('status_stage', 'designing')
            ->exists();

        // Jika belum ada, buat
        if (!$hasDesigning) {
            StatusHistory::create([
                'order_id' => $orderId,
                'status_stage' => 'designing',
                'updated_by' => $userId,
                'start_time' => now(),
            ]);
        }

        $latestStatus = $order->statusHistory()->latest('start_time')->first();

        DB::commit();
        return response()->json([
            'success' => true,
            'message' => 'You are already working on this design',
            'data' => [
                'order_id' => $orderId,
                'design_id' => $order->design->id,
                'status' => $latestStatus?->status_stage ?? 'designing',
                'assigned_to' => $userId,
            ],
        ]);
    }

        // ✅ Jika belum di-assign, assign sekarang
        $order->design->update([
            'assigned_to' => $userId,
        ]);

        // Create status history
        StatusHistory::create([
            'order_id' => $orderId,
            'status_stage' => 'designing',
            'updated_by' => $userId,
            'start_time' => now(),
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Design task started',
            'data' => [
                'order_id' => $orderId,
                'design_id' => $order->design->id,
                'status' => 'designing',
                'assigned_to' => $userId,
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

    public function storeItem(Request $request)
    {
        $request->validate([
            'design_id' => 'required|exists:designs,id',
            'design_file' => 'required|file|mimes:jpg,jpeg,png,pdf,ai,psd|max:10240',
            'design_notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $userId = auth()->user()->id;
            $design = Design::findOrFail($request->input('design_id'));

            // Verify user punya hak
            if ($design->assigned_to !== $userId) {
                throw new \Exception('You do not have permission to upload for this design');
            }

            // Upload file
            $file = $request->file('design_file');
            $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
            $path = $file->storeAs('designs', $filename, 'public');

            if (!$path) {
                throw new \Exception('Failed to upload file');
            }

            // Create design item
            $designItem = DesignItem::create([
                'design_id' => $design->id,
                'design_file' => $path,
                'design_notes' => $request->input('design_notes') ?? null,
                'design_status' => 'in_progress', // Status awal
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Design item uploaded',
                'data' => [
                    'id' => $designItem->id,
                    'design_id' => $designItem->design_id,
                    'file_url' => asset('storage/' . $path),
                    'file_name' => $filename,
                    'status' => $designItem->design_status,
                    'created_at' => $designItem->created_at->format('d M Y H:i:s'),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/designer/design-items/{itemId}
     * UPDATE: Update design item (file/notes)
     */
    public function updateItem(Request $request, $itemId)
    {
        $request->validate([
            'design_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,ai,psd|max:10240',
            'design_notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $designItem = DesignItem::with('design')->findOrFail($itemId);

            // Cek user assigned ke design
            if ($designItem->design->assigned_to !== auth()->id()) {
                throw new \Exception('Forbidden - This item is not assigned to you');
            }

            // Hanya item in_progress yang boleh diedit
            if ($designItem->design_status !== 'in_progress') {
                throw new \Exception('Cannot update item with status: ' . $designItem->design_status);
            }

            $updateData = [];

            // Update file jika ada
            if ($request->hasFile('design_file')) {
                $file = $request->file('design_file');
                $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                $path = $file->storeAs('designs', $filename, 'public');

                if (!$path) {
                    throw new \Exception('Failed to upload file');
                }

                $updateData['design_file'] = $path;
            }

            // Update notes jika ada
            if ($request->filled('design_notes')) {
                $updateData['design_notes'] = $request->input('design_notes');
            }

            // Perform update
            if (!empty($updateData)) {
                $designItem->update($updateData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Design item updated',
                'data' => [
                    'id' => $designItem->id,
                    'design_id' => $designItem->design_id,
                    'file_url' => asset('storage/' . $designItem->design_file),
                    'file_name' => basename($designItem->design_file),
                    'notes' => $designItem->design_notes,
                    'status' => $designItem->design_status,
                    'updated_at' => $designItem->updated_at->format('d M Y H:i:s'),
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

    /**
     * DELETE /api/designer/design-items/{itemId}
     * DESTROY: Hapus design item
     */
    public function destroyItem(Request $request, $itemId)
    {
        DB::beginTransaction();
        try {
            $userId = auth()->user()->id;
            $designItem = DesignItem::with('design')->findOrFail($itemId);

            // Verify user punya hak
            if ($designItem->design->assigned_to !== $userId) {
                throw new \Exception('You do not have permission to delete this item');
            }

            // Cek status masih revision
            if ($designItem->design_status !== 'revision') {
                throw new \Exception('Cannot delete design item with status: ' . $designItem->design_status);
            }

            $designItem->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Design item deleted',
                'data' => [
                    'deleted_id' => $itemId,
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
