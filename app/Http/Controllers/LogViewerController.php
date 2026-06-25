<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class LogViewerController extends Controller
{
    public function __invoke(Request $request, ?string $file = null): View
    {
        $user = $request->user();

        abort_unless(method_exists($user, 'isAdmin') && $user->isAdmin(), Response::HTTP_FORBIDDEN);

        $logs = $this->dailyLogs();
        $selectedFile = $this->selectedFile($logs, $file);

        return view('logs.index', [
            'logs' => $logs,
            'selectedFile' => $selectedFile,
            'selectedLog' => $selectedFile === null ? null : $logs->firstWhere('file', $selectedFile),
            'contents' => $selectedFile === null ? '' : File::get(storage_path("logs/{$selectedFile}")),
        ]);
    }

    /**
     * @return Collection<int, array{file: string, date: string, display_date: string, size: string, updated_at: Carbon}>
     */
    private function dailyLogs(): Collection
    {
        return collect(File::glob(storage_path('logs/laravel-*.log')) ?: [])
            ->map(function (string $path): ?array {
                $file = basename($path);

                if (! preg_match('/^laravel-(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
                    return null;
                }

                $date = Carbon::createFromFormat('Y-m-d', $matches[1]);

                return [
                    'file' => $file,
                    'date' => $matches[1],
                    'display_date' => $date->format('d M Y'),
                    'size' => $this->humanFileSize(File::size($path)),
                    'updated_at' => Carbon::createFromTimestamp(File::lastModified($path)),
                ];
            })
            ->filter()
            ->sortByDesc('date')
            ->values();
    }

    /**
     * @param  Collection<int, array{file: string}>  $logs
     */
    private function selectedFile(Collection $logs, ?string $file): ?string
    {
        if ($logs->isEmpty()) {
            return null;
        }

        if ($file === null) {
            return $logs->first()['file'];
        }

        abort_unless(
            preg_match('/^laravel-\d{4}-\d{2}-\d{2}\.log$/', $file) === 1
                && $logs->contains('file', $file),
            Response::HTTP_NOT_FOUND
        );

        return $file;
    }

    private function humanFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
