<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Design;
use App\Services\LayoutToElementorService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('design:export-elementor {designId} {--format=classic} {--output=}', function () {
    $designId = (int) $this->argument('designId');
    $format = (string) $this->option('format');
    $output = (string) $this->option('output');

    $design = Design::query()->findOrFail($designId);

    /** @var LayoutToElementorService $exporter */
    $exporter = app(LayoutToElementorService::class);
    $payload = $exporter->export($design->layout_json, $design->name, $format);
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

    if ($output === '') {
        $slug = Str::slug($design->name) ?: ('design-' . $design->id);
        $suffix = ($format === LayoutToElementorService::FORMAT_CONTAINER) ? '-container' : '';
        if ($format === LayoutToElementorService::FORMAT_CLASSIC_SIMPLE) {
            $suffix = '-simple';
        }
        $output = 'elementor-exports/' . $slug . '-elementor' . $suffix . '.json';
    }

    $output = ltrim($output, '/');

    Storage::disk('local')->put($output, $json);

    $this->info('Saved: storage/app/' . $output);
})->purpose('Export a design as Elementor JSON to storage/app');
