@extends('layouts.app')

@section('title', ($movie['title'] ?? 'Chi Tiết Phim') . ' - CineBook')

@section('content')
    <!-- Movie Hero -->
    <section class="relative">
        <!-- Backdrop -->
        <div class="absolute inset-0 h-[500px]">
            <img src="{{ $movie['backdrop_url'] ?? $movie['poster_url'] ?? 'https://via.placeholder.com/1920x500/1f2937/6b7280' }}"
                alt="{{ $movie['title'] }}" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-[#0f0f0f] via-[#0f0f0f]/80 to-transparent"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-[#0f0f0f] via-transparent to-transparent"></div>
        </div>

        <!-- Content -->
        <div class="relative container mx-auto px-4 pt-8 pb-12">
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Poster -->
                <div class="flex-shrink-0">
                    <img src="{{ $movie['poster_url'] ?? 'https://via.placeholder.com/300x450/1f2937/6b7280?text=No+Image' }}"
                        alt="{{ $movie['title'] }}" class="w-64 h-96 object-cover rounded-xl shadow-2xl mx-auto md:mx-0">
                </div>

                <!-- Info -->
                <div class="flex-1 text-center md:text-left">
                    <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">{{ $movie['title'] }}</h1>

                    <!-- Meta -->
                    <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 text-gray-400 mb-6">
                        @if(isset($movie['rating']) && !empty($movie['rating']))
                            <span class="px-3 py-1 bg-red-600 text-white text-sm font-bold rounded">
                                {{ $movie['rating'] }}
                            </span>
                        @endif

                        @if(isset($movie['duration']))
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ $movie['duration'] }} phút
                            </span>
                        @endif

                        @if(isset($movie['release_date']))
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ date('d/m/Y', strtotime($movie['release_date'])) }}
                            </span>
                        @endif
                    </div>

                    <!-- Genres -->
                    @if(isset($movie['genre']))
                        <div class="flex flex-wrap justify-center md:justify-start gap-2 mb-6">
                            @php
                                $genres = is_array($movie['genre']) ? $movie['genre'] : explode(',', $movie['genre']);
                            @endphp
                            @foreach($genres as $genre)
                                <span class="px-3 py-1 bg-dark-200 border border-gray-700 rounded-full text-sm text-gray-300">
                                    {{ trim($genre) }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <!-- Description -->
                    @if(isset($movie['description']))
                        <p class="text-gray-300 leading-relaxed mb-8 max-w-2xl mx-auto md:mx-0">
                            {{ $movie['description'] }}
                        </p>
                    @endif

                    <!-- Additional Info -->
                    <div class="grid grid-cols-2 gap-4 mb-8 max-w-lg mx-auto md:mx-0">
                        @if(isset($movie['director']))
                            <div>
                                <span class="text-gray-500 text-sm">Đạo Diễn</span>
                                <p class="text-white">{{ $movie['director'] }}</p>
                            </div>
                        @endif

                        @if(isset($movie['cast']))
                            <div>
                                <span class="text-gray-500 text-sm">Diễn Viên</span>
                                <p class="text-white">
                                    {{ is_array($movie['cast']) ? implode(', ', $movie['cast']) : $movie['cast'] }}</p>
                            </div>
                        @endif

                        @if(isset($movie['language']))
                            <div>
                                <span class="text-gray-500 text-sm">Ngôn Ngữ</span>
                                <p class="text-white">{{ $movie['language'] }}</p>
                            </div>
                        @endif

                        @if(isset($movie['country']))
                            <div>
                                <span class="text-gray-500 text-sm">Quốc Gia</span>
                                <p class="text-white">{{ $movie['country'] }}</p>
                            </div>
                        @endif
                    </div>

                    <!-- CTA Button -->
                    @if(isset($movie['is_now_showing']) && $movie['is_now_showing'])
                        <a href="#showtimes"
                            class="btn-primary inline-flex items-center px-8 py-3 rounded-full text-white font-semibold">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                            </svg>
                            Đặt Vé Ngay
                        </a>
                    @elseif(isset($movie['trailer_url']))
                        <a href="{{ $movie['trailer_url'] }}" target="_blank"
                            class="inline-flex items-center px-8 py-3 rounded-full border border-gray-600 text-white font-semibold hover:bg-white/10 transition">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Xem Trailer
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <!-- Showtimes Section -->
    <section id="showtimes" class="py-12 bg-dark-100">
        <div class="container mx-auto px-4">
            <h2 class="text-2xl font-bold text-white mb-8">Lịch Chiếu</h2>

            <!-- Date Selector -->
            <div class="flex overflow-x-auto pb-4 mb-8 -mx-4 px-4 space-x-3" id="date-selector">
                @for($i = 0; $i < 7; $i++)
                    @php
                        $date = now()->addDays($i);
                        $isToday = $i === 0;
                    @endphp
                    <button
                        class="flex-shrink-0 px-6 py-3 rounded-xl border transition date-btn {{ $isToday ? 'bg-red-600 border-red-600 text-white' : 'bg-dark-200 border-gray-700 text-gray-400 hover:border-gray-500' }}"
                        data-date="{{ $date->format('Y-m-d') }}" onclick="selectDate(this)">
                        <div class="text-sm">{{ $isToday ? 'Hôm Nay' : $date->locale('vi')->dayName }}</div>
                        <div class="text-lg font-semibold">{{ $date->format('d/m') }}</div>
                    </button>
                @endfor
            </div>

            <!-- Showtimes List -->
            <div id="showtimes-container">
                @if(count($showtimes) > 0)
                    @php
                        $groupedShowtimes = collect($showtimes)->groupBy('cinema_name');
                    @endphp

                    @foreach($groupedShowtimes as $cinema => $times)
                        <div class="bg-dark-200 rounded-xl p-6 border border-gray-800 mb-4">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-white">{{ $cinema }}</h3>
                                    @if(isset($times[0]['cinema_address']))
                                        <p class="text-gray-400 text-sm">{{ $times[0]['cinema_address'] }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                @foreach($times as $showtime)
                                    <a href="{{ route('booking.seats', $showtime['id']) }}"
                                        class="px-4 py-2 bg-dark-300 border border-gray-700 rounded-lg text-white hover:border-red-500 hover:bg-red-600/20 transition">
                                        <span
                                            class="font-medium">{{ date('H:i', strtotime($showtime['show_time'] ?? $showtime['start_time'] ?? '00:00')) }}</span>
                                        @if(isset($showtime['format']))
                                            <span class="text-xs text-gray-400 ml-1">{{ $showtime['format'] }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-12">
                        <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-gray-400">Chưa có lịch chiếu cho ngày này</p>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        function selectDate(btn) {
            // Update active state
            document.querySelectorAll('.date-btn').forEach(b => {
                b.classList.remove('bg-red-600', 'border-red-600', 'text-white');
                b.classList.add('bg-dark-200', 'border-gray-700', 'text-gray-400');
            });
            btn.classList.remove('bg-dark-200', 'border-gray-700', 'text-gray-400');
            btn.classList.add('bg-red-600', 'border-red-600', 'text-white');

            // Fetch showtimes for selected date
            const date = btn.dataset.date;
            fetchShowtimes(date);
        }

        function fetchShowtimes(date) {
            const movieId = '{{ $movie['id'] }}';
            const container = document.getElementById('showtimes-container');

            container.innerHTML = `
            <div class="text-center py-12">
                <div class="animate-spin w-10 h-10 border-4 border-red-600 border-t-transparent rounded-full mx-auto"></div>
                <p class="text-gray-400 mt-4">Đang tải lịch chiếu...</p>
            </div>
        `;

            fetch(`/movies/${movieId}/showtimes?date=${date}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data && data.data.length > 0) {
                        renderShowtimes(data.data);
                    } else {
                        container.innerHTML = `
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-gray-400">Chưa có lịch chiếu cho ngày này</p>
                        </div>
                    `;
                    }
                })
                .catch(err => {
                    container.innerHTML = `
                    <div class="text-center py-12">
                        <p class="text-red-400">Có lỗi xảy ra. Vui lòng thử lại.</p>
                    </div>
                `;
                });
        }

        function renderShowtimes(showtimes) {
            const container = document.getElementById('showtimes-container');
            const grouped = {};

            showtimes.forEach(st => {
                const cinema = st.cinema_name || 'CineBook Cinema';
                if (!grouped[cinema]) {
                    grouped[cinema] = {
                        address: st.cinema_address || 'TP. Hồ Chí Minh',
                        times: []
                    };
                }
                grouped[cinema].times.push(st);
            });

            let html = '';
            for (const [cinema, data] of Object.entries(grouped)) {
                html += `
                <div class="bg-dark-200 rounded-xl p-6 border border-gray-800 mb-4">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-white">${cinema}</h3>
                            ${data.address ? `<p class="text-gray-400 text-sm">${data.address}</p>` : ''}
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3">
            `;

                data.times.forEach(st => {
                    // Handle show_time format (could be "HH:mm:ss" or ISO date string)
                    let timeStr = 'N/A';
                    if (st.start_time) {
                        const time = new Date(st.start_time);
                        if (!isNaN(time.getTime())) {
                            timeStr = time.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
                        } else {
                            timeStr = st.start_time.substring(0, 5);
                        }
                    } else if (st.show_time) {
                        // show_time is in "HH:mm:ss" format
                        timeStr = st.show_time.substring(0, 5);
                    }
                    html += `
                    <a href="/booking/seats/${st.id}" class="px-4 py-2 bg-dark-300 border border-gray-700 rounded-lg text-white hover:border-red-500 hover:bg-red-600/20 transition">
                        <span class="font-medium">${timeStr}</span>
                        ${st.format ? `<span class="text-xs text-gray-400 ml-1">${st.format}</span>` : ''}
                    </a>
                `;
                });

                html += '</div></div>';
            }

            container.innerHTML = html;
        }
    </script>
@endpush