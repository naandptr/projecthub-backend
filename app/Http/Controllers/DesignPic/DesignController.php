<?php

namespace App\Http\Controllers\DesignPic;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Design;
use App\Models\DesignItem;
use App\Models\StatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DesignController extends Controller
{
    /* GET ALL DESIGNS */
    public function index()
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
                        'order_file_url' => asset('storage/' . $design->order->order_file),
                        'order_file_name' => basename($design->order->order_file),
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

    /* GET DESIGN BY ID */
    public function show($orderId)
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
                        'cust_phone' => $order->cust_phone,
                        'cust_address' => $order->cust_address,
                        'product_name' => $order->product_name,
                        'product_price' => number_format($order->product_price, 0, ',', '.'),
                        'product_quantity' => $order->product_quantity,
                        'order_date' => $order->order_date,
                        'order_deadline' => $order->order_deadline,
                        'order_file_url' => asset('storage/' . $order->order_file),
                        'order_file_name' => basename($order->order_file),
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
                                'design_notes' => $item->design_notes,
                                'design_status' => $item->design_status,
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

    /* START DESIGN */
    public function start($orderId)
    {
        DB::beginTransaction();
        try {
            $userId = auth()->user()->id;

            $order = Order::with('design')->findOrFail($orderId);

            // Validate design exists
            if (!$order->design) {
                throw new \Exception('Design not found for this order');
            }

            // Prevent starting design if already assigned to another designer
            if ($order->design->assigned_to !== null && $order->design->assigned_to !== $userId) {
                throw new \Exception('This order already assigned to another designer');
            }

            // Handle case where designer is resuming their own design work
            if ($order->design->assigned_to === $userId) {
                $hasDesigning = StatusHistory::where('order_id', $orderId)
                    ->where('status_stage', 'designing')
                    ->exists();

                // Create 'designing' status if it doesn't exist yet
                if (!$hasDesigning) {
                    StatusHistory::where('order_id', $orderId)
                        ->whereNull('end_time')
                        ->update([
                            'end_time' => now()
                    ]);

                    // Create new 'designing' status
                    StatusHistory::create([
                        'order_id' => $orderId,
                        'status_stage' => 'designing',
                        'updated_by' => Auth::id(),
                        'start_time' => now(),
                        'end_time' => null
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

            // Assign the design to current user (first-time assignment)
            $order->design->update([
                'assigned_to' => $userId,
            ]);

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

    /* ADD DESIGN ITEM */
    public function storeItem(Request $request, $itemId)
    {       
        $request->validate([
            'design_file' => 'required|file|mimes:jpg,jpeg,png,pdf,ai,psd|max:10240',
            'design_notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            $userId = auth()->user()->id;
            $designId = (int) $itemId;
            $design = Design::find($designId);
            
            // Validate design exists
            if (!$design) {
                throw new \Exception("Design ID {$designId} not found");
            }
            
            // Ensure only assigned designer can upload design items
            if ($design->assigned_to !== $userId) {
                throw new \Exception('Forbidden - You do not have permission to upload for this design');
            }            

            // Handle file upload with compression for images
            $file = $request->file('design_file');
            $path = null;  // Initialize path variable
        
            // Compress and store image files to save storage space
            if (str_starts_with($file->getMimeType(), 'image/')) {
                $path = Design::compressAndStoreImage($file);
            } else {
                // Store non-image files (PDF, AI, PSD) as is
                $path = $file->store('designs', 'public');
            }
        
            // Validate file was stored successfully
            if (!$path) {
                throw new \Exception('Failed to upload file to storage');
            }    
            
            // Create design item record with uploaded file
            $designItem = DesignItem::create([
                'design_id' => $designId,
                'design_file' => $path,  // Use the stored file path
                'design_notes' => $request->input('design_notes') ?? null,
                'design_status' => 'in_progress',  // Initial status awaiting review
            ]);

            if (!$designItem) {
                throw new \Exception('Failed to create design item record');
            } 
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Design item uploaded successfully',
                'data' => [
                    'item_id' => $designItem->id,
                    'design_id' => $designItem->design_id,
                    'file_url' => asset('storage/' . $designItem->design_file),
                    'file_name' => basename($designItem->design_file),
                    'notes' => $designItem->design_notes,
                    'status' => $designItem->design_status,
                    'created_at' => $designItem->created_at->format('d M Y H:i:s'),
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

    /* UPDATE DESIGN ITEM */
    public function updateItem(Request $request, $itemId)
    {
        $request->validate([
            'design_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,ai,psd|max:10240',
            'design_notes' => 'nullable|string|max:500',
        ], [
            'design_file.mimes' => 'File harus jpg, jpeg, png, pdf, ai, atau psd',
            'design_file.max' => 'Ukuran file maksimal 10MB',
        ]);

        DB::beginTransaction();
        try {
            $itemId = (int) $itemId;            
            $designItem = DesignItem::with('design')->find($itemId);

            if (!$designItem) {
                throw new \Exception("Design item ID {$itemId} not found");
            }            
            
            if ($designItem->design->assigned_to !== auth()->user()->id) {
                throw new \Exception('Forbidden - This item is not assigned to you');
            }            
            
            // Only allow updates for items in 'in_progress' or 'revision' status
            if (!in_array($designItem->design_status, ['in_progress', 'revision'])) {
                throw new \Exception(
                    "Cannot update item with status '{$designItem->design_status}'. " .
                    "Only 'in_progress' or 'revision' items can be edited."
                );
            }

            $updateData = [];
            $oldFile = $designItem->design_file; 
            $newPath = null;  // Initialize path variable for cleanup on error           
            
            if ($request->hasFile('design_file')) {
                $file = $request->file('design_file');
            
                // Compress and store image files to save storage space
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $newPath = Design::compressAndStoreImage($file);
                } else {
                    // Store non-image files (PDF, AI, PSD) as is
                    $newPath = $file->store('designs', 'public');
                }

                // Validate file was stored successfully
                if (!$newPath) {
                    throw new \Exception('Failed to upload new file');
                }

                $updateData['design_file'] = $newPath;
            }            
            
            if ($request->has('design_notes')) {
                $notes = $request->input('design_notes');
                $updateData['design_notes'] = $notes;
            }            
            
            if (empty($updateData)) {
                DB::commit();
                return response()->json([
                    'success' => false,
                    'message' => 'No changes to update. Please provide design_file or design_notes.',
                ], 422);
            }            
            
            $affectedRows = $designItem->update($updateData);            
            
            if (!$affectedRows) {
                throw new \Exception('Update failed - no rows affected');
            }            
            
            if (isset($updateData['design_file']) && $oldFile && Storage::disk('public')->exists($oldFile)) {
                Storage::disk('public')->delete($oldFile);
            }            
            
            DB::commit();            
            
            $designItem->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Design item updated successfully',
                'data' => [
                    'item_id' => $designItem->id,
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
            
            if (isset($newPath) && Storage::disk('public')->exists($newPath)) {
                Storage::disk('public')->delete($newPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* DELETE DESIGN ITEM */
    public function destroyItem($itemId)
    {
        DB::beginTransaction();
        try {
            $userId = auth()->user()->id;
            $designItem = DesignItem::with('design')->findOrFail($itemId);            
            
            if ($designItem->design->assigned_to !== $userId) {
                throw new \Exception('You do not have permission to delete this item');
            }            
            
            // Only allow deletion of items with 'revision' status (rejected designs)
            if ($designItem->design_status !== 'revision') {
                throw new \Exception('Cannot delete design item with status: ' . $designItem->design_status);
            }

            // Delete associated file from storage if it exists
            if ($designItem->design_file && Storage::disk('public')->exists($designItem->design_file)) {
                Storage::disk('public')->delete($designItem->design_file);
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
