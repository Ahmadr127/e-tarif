<?php

namespace Tests\Feature;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Halaman error custom di resources/views/errors.
 */
class ErrorPagesTest extends TestCase
{
    public function test_custom_error_views_render_with_expected_content(): void
    {
        $pages = [
            '400' => ['Permintaan Tidak Valid', 'Kembali ke Beranda'],
            '401' => ['Belum Masuk', 'Masuk'],
            '403' => ['Akses Ditolak', 'Kembali ke Beranda'],
            '404' => ['Halaman Tidak Ditemukan', 'Kembali ke Beranda'],
            '419' => ['Sesi Kedaluwarsa', 'Muat Ulang Halaman'],
            '429' => ['Terlalu Banyak Permintaan', 'Kembali ke Beranda'],
            '500' => ['Terjadi Kesalahan Server', 'Kembali ke Beranda'],
            '503' => ['Layanan Tidak Tersedia', 'Kembali ke Beranda'],
        ];

        foreach ($pages as $code => [$title, $action]) {
            $this->view('errors.'.$code)
                ->assertSee($code)
                ->assertSee($title)
                ->assertSee($action);
        }
    }

    public function test_series_fallback_uses_the_exception_status_code(): void
    {
        $this->view('errors.4xx', ['exception' => new NotFoundHttpException()])
            ->assertSee('404')
            ->assertSee('Permintaan Gagal');

        $this->view('errors.5xx', ['exception' => new HttpException(502)])
            ->assertSee('502')
            ->assertSee('Terjadi Kesalahan Server');
    }

    public function test_unknown_url_renders_custom_404_page(): void
    {
        config(['app.debug' => false]);

        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman Tidak Ditemukan')
            ->assertSee('404')
            ->assertSee('Buku Tarif');
    }
}
