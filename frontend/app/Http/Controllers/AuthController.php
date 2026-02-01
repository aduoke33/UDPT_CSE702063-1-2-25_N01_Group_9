<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Show login form
     */
    public function showLogin()
    {
        if ($this->authService->isAuthenticated()) {
            return redirect()->route('home');
        }
        
        return view('auth.login');
    }

    /**
     * Handle login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $response = $this->authService->login(
            $request->input('email'),
            $request->input('password')
        );

        if ($response['success']) {
            $redirect = Session::get('url.intended', route('home'));
            Session::forget('url.intended');
            
            return redirect($redirect)->with('success', 'Đăng nhập thành công!');
        }

        // Xử lý thông báo lỗi chi tiết
        $errorMessage = $this->getLoginErrorMessage($response);
        
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $errorMessage]);
    }

    /**
     * Show registration form
     */
    public function showRegister()
    {
        if ($this->authService->isAuthenticated()) {
            return redirect()->route('home');
        }
        
        return view('auth.register');
    }

    /**
     * Handle registration
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'password' => 'required|min:6|confirmed',
        ]);

        $response = $this->authService->register([
            'username' => $request->input('email'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'full_name' => $request->input('name'),
            'phone' => $request->input('phone'),
        ]);

        if ($response['success']) {
            // Auto login after registration
            $loginResponse = $this->authService->login(
                $request->input('email'),
                $request->input('password')
            );
            
            if ($loginResponse['success']) {
                return redirect()->route('home')->with('success', 'Đăng ký thành công! Chào mừng bạn đến với CineBook!');
            }
            
            return redirect()->route('login')->with('success', 'Đăng ký thành công! Vui lòng đăng nhập.');
        }

        // Xử lý thông báo lỗi chi tiết
        $errorMessage = $this->getRegisterErrorMessage($response);
        
        return back()
            ->withInput($request->except('password', 'password_confirmation'))
            ->withErrors(['email' => $errorMessage]);
    }

    /**
     * Handle logout
     */
    public function logout()
    {
        $this->authService->logout();
        
        return redirect()->route('home')->with('success', 'Đăng xuất thành công!');
    }

    /**
     * Get detailed login error message
     */
    private function getLoginErrorMessage(array $response): string
    {
        $error = $response['error'] ?? $response['message'] ?? '';
        $status = $response['status'] ?? 0;
        
        // Kiểm tra các lỗi cụ thể
        if ($status === 401 || stripos($error, 'invalid') !== false || stripos($error, 'incorrect') !== false) {
            return 'Email hoặc mật khẩu không chính xác. Vui lòng kiểm tra lại.';
        }
        
        if ($status === 404 || stripos($error, 'not found') !== false || stripos($error, 'not exist') !== false) {
            return 'Tài khoản không tồn tại. Vui lòng kiểm tra email hoặc đăng ký tài khoản mới.';
        }
        
        if ($status === 403 || stripos($error, 'blocked') !== false || stripos($error, 'disabled') !== false) {
            return 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ hỗ trợ.';
        }
        
        if ($status === 429 || stripos($error, 'too many') !== false) {
            return 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau ít phút.';
        }
        
        if (stripos($error, 'connection') !== false || stripos($error, 'timeout') !== false) {
            return 'Lỗi kết nối máy chủ. Vui lòng thử lại sau.';
        }
        
        // Thông báo mặc định
        return $error ?: 'Đăng nhập thất bại. Vui lòng kiểm tra lại thông tin đăng nhập.';
    }

    /**
     * Get detailed register error message
     */
    private function getRegisterErrorMessage(array $response): string
    {
        $error = $response['error'] ?? $response['message'] ?? '';
        $status = $response['status'] ?? 0;
        
        // Kiểm tra các lỗi cụ thể
        if ($status === 409 || stripos($error, 'exist') !== false || stripos($error, 'duplicate') !== false || stripos($error, 'already') !== false) {
            return 'Email này đã được đăng ký. Vui lòng sử dụng email khác hoặc đăng nhập.';
        }
        
        if (stripos($error, 'email') !== false && stripos($error, 'invalid') !== false) {
            return 'Địa chỉ email không hợp lệ. Vui lòng nhập email đúng định dạng.';
        }
        
        if (stripos($error, 'password') !== false) {
            return 'Mật khẩu không đáp ứng yêu cầu. Vui lòng sử dụng mật khẩu mạnh hơn (ít nhất 6 ký tự).';
        }
        
        if (stripos($error, 'phone') !== false) {
            return 'Số điện thoại không hợp lệ. Vui lòng kiểm tra lại.';
        }
        
        if (stripos($error, 'connection') !== false || stripos($error, 'timeout') !== false) {
            return 'Lỗi kết nối máy chủ. Vui lòng thử lại sau.';
        }
        
        // Thông báo mặc định
        return $error ?: 'Đăng ký thất bại. Vui lòng kiểm tra lại thông tin và thử lại.';
    }

    /**
     * Show user profile
     */
    public function profile()
    {
        $user = $this->authService->getUser();
        
        return view('auth.profile', compact('user'));
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        $result = $this->authService->updateProfile([
            'full_name' => $request->input('name'),
            'phone_number' => $request->input('phone'),
        ]);

        if ($result['success']) {
            return back()->with('success', 'Cập nhật thông tin thành công!');
        }

        return back()->withErrors(['error' => $result['message'] ?? 'Cập nhật thất bại. Vui lòng thử lại.']);
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        // Get current user
        $user = $this->authService->getUser();
        
        if (!$user) {
            return back()->withErrors(['error' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.']);
        }

        // Call API to change password
        $result = $this->authService->changePassword(
            $request->input('current_password'),
            $request->input('new_password')
        );

        if ($result['success']) {
            return back()->with('success', 'Đổi mật khẩu thành công!');
        }

        // Handle specific error messages
        $errorMessage = $result['message'] ?? 'Đổi mật khẩu thất bại.';
        if (stripos($errorMessage, 'incorrect') !== false || stripos($errorMessage, 'invalid') !== false) {
            return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không chính xác.']);
        }

        return back()->withErrors(['error' => $errorMessage]);
    }
}
