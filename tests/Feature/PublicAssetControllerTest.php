<?php

use Illuminate\Support\Facades\File;
use Redberry\MailboxForLaravel\Http\Controllers\PublicAssetController;

describe(PublicAssetController::class, function () {
    beforeEach(function () {
        // Ensure we have a clean dist directory for testing
        $testDir = __DIR__.'/../../dist/test-assets';
        if (! File::exists($testDir)) {
            File::makeDirectory($testDir, 0755, true);
        }

        // Create test files
        File::put($testDir.'/test-file.js', 'console.log("test");');
        File::put($testDir.'/test-file.css', 'body { color: red; }');
        File::put($testDir.'/test-file.js.map', '{"version":3}');
        File::put($testDir.'/unknown-file.xyz', 'binary data');
    });

    afterEach(function () {
        // Clean up test files
        $testDir = __DIR__.'/../../dist/test-assets';
        if (File::exists($testDir)) {
            File::deleteDirectory($testDir);
        }
    });

    it('serves JavaScript files with correct content type', function () {
        $controller = new PublicAssetController;
        $response = $controller->__invoke('test-assets/test-file.js');

        expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\BinaryFileResponse::class);
        expect($response->headers->get('Content-Type'))->toContain('application/javascript');
    });

    it('serves CSS files with correct content type', function () {
        $controller = new PublicAssetController;
        $response = $controller->__invoke('test-assets/test-file.css');

        expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\BinaryFileResponse::class);
        expect($response->headers->get('Content-Type'))->toContain('text/css');
    });

    it('serves source map files with correct content type', function () {
        $controller = new PublicAssetController;
        $response = $controller->__invoke('test-assets/test-file.js.map');

        expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\BinaryFileResponse::class);
        expect($response->headers->get('Content-Type'))->toContain('application/json');
    });

    it('returns 404 for non-existent files', function () {
        $controller = new PublicAssetController;

        // This should trigger an abort(404)
        expect(fn () => $controller->__invoke('non-existent-file.js'))
            ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('uses fallback mime type for unknown file extensions', function () {
        $controller = new PublicAssetController;
        $response = $controller->__invoke('test-assets/unknown-file.xyz');

        expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\BinaryFileResponse::class);
        // Should fall back to File::mimeType() detection
    });

    it('sets appropriate cache headers for asset files', function () {
        $controller = new PublicAssetController;
        $response = $controller->__invoke('test-assets/test-file.js');

        $cacheControl = $response->headers->get('Cache-Control');
        expect($cacheControl)->toContain('public');
        expect($cacheControl)->toContain('max-age=31536000');
        expect($cacheControl)->toContain('immutable');
    });

    it('determines correct MIME types based on file extensions', function () {
        $controller = new PublicAssetController;

        // Test JS files
        $jsResponse = $controller->__invoke('test-assets/test-file.js');
        expect($jsResponse->headers->get('Content-Type'))->toContain('application/javascript');

        // Test CSS files
        $cssResponse = $controller->__invoke('test-assets/test-file.css');
        expect($cssResponse->headers->get('Content-Type'))->toContain('text/css');

        // Test map files
        $mapResponse = $controller->__invoke('test-assets/test-file.js.map');
        expect($mapResponse->headers->get('Content-Type'))->toContain('application/json');
    });
});
