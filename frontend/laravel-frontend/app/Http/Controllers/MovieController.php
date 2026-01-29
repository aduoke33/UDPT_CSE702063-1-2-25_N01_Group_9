<?php

namespace App\Http\Controllers;

use App\Services\Api\MovieApiService;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    protected MovieApiService $movieApi;

    public function __construct(MovieApiService $movieApi)
    {
        $this->movieApi = $movieApi;
    }

    /**
     * Trang chủ: Hiển thị danh sách phim
     */
    public function index() {
        /** @var \Illuminate\Http\Client\Response $response */
        $response = $this->movieApi->getAllMovies();
        $data = $response->json();

        // Kiểm tra xem dữ liệu trả về có phải là danh sách phim không
        // Nếu Backend trả về mảng có key 'service' thì nghĩa là đang gọi nhầm Health Check
        if (isset($data['service']) || !is_array($data) || empty($data)) {
            $movies = []; // Trả về mảng rỗng để giao diện hiện "Chưa có phim" thay vì bị lỗi 500
        } else {
            $movies = $data;
        }

        return view('movies.index', compact('movies'));
    }

    /**
     * Chi tiết phim: Hiện thời lượng, giờ chiếu
     */
    public function show($id)
    {
        /** @var \Illuminate\Http\Client\Response $response */
        $response = $this->movieApi->getMovieDetail($id);

        if (!$response->successful()) {
            abort(404, 'Không tìm thấy phim');
        }

        $movie = $response->json();
        return view('movies.show', compact('movie'));
    }
}