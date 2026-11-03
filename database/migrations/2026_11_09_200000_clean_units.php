<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Units clean-up.
 *
 * 1. "Kilogram" and "Kg" were the same unit entered twice (Kilogram was then deleted, Kg kept and used by products).
 *    The duplicates are merged into one unit named "Kilogram" (the full-word style of Gram, Litre, Piece ...):
 *    every product pointing at any of them is moved to the kept row, and the other rows are removed when nothing
 *    refers to them any more. The kept row is the one products already use, so no product changes unit.
 * 2. Common units that were missing are added (only when no unit of that name exists yet).
 *
 * down() does not undo the merge (it cannot tell which product had which spelling); it only removes the units
 * this migration added when no product uses them.
 */
return new class extends Migration
{
    private const ADDED = ['Metre', 'Centimetre', 'Feet', 'Square Feet', 'Carton', 'Bottle', 'Can', 'Roll', 'Bag', 'Strip', 'Tube'];

    public function up(): void
    {
        $now = now();

        // 1. Merge Kilogram / Kg / Kgs / KG.
        $kilo = DB::table('units')->whereRaw("LOWER(TRIM(name)) IN ('kilogram', 'kilograms', 'kg', 'kgs', 'kilo')")->orderBy('id')->get();
        if ($kilo->isNotEmpty()) {
            $used = DB::table('products')->whereIn('unit_id', $kilo->pluck('id'))->groupBy('unit_id')
                ->selectRaw('unit_id, COUNT(*) n')->orderByDesc('n')->pluck('n', 'unit_id');
            $keep = $used->keys()->first() ?? ($kilo->firstWhere('deleted', 'No')->id ?? $kilo->first()->id);

            DB::table('units')->where('id', $keep)->update([
                'name' => 'Kilogram', 'slug' => 'kilogram', 'deleted' => 'No', 'status' => 'Active',
                'deleted_by' => null, 'deleted_date' => null, 'updated_date' => $now, 'updated_at' => $now,
            ]);

            $others = $kilo->pluck('id')->reject(fn ($id) => (int) $id === (int) $keep)->all();
            if ($others) {
                DB::table('products')->whereIn('unit_id', $others)->update(['unit_id' => $keep]);
                DB::table('units')->whereIn('id', $others)->delete();
            }
        }

        // 2. Missing common units.
        foreach (self::ADDED as $name) {
            if (DB::table('units')->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->where('deleted', 'No')->exists()) {
                continue;
            }
            DB::table('units')->insert([
                'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'deleted' => 'No', 'status' => 'Active',
                'created_date' => $now, 'created_by' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::ADDED as $name) {
            $id = DB::table('units')->where('name', $name)->value('id');
            if ($id && !DB::table('products')->where('unit_id', $id)->exists()) {
                DB::table('units')->where('id', $id)->delete();
            }
        }
    }
};
