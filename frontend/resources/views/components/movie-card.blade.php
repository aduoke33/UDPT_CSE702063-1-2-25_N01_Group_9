@props(['movie'])

<div class="card-hover bg-dark-200 rounded-xl overflow-hidden group">
    <a href="{{ route('movies.show', $movie['id']) }}" class="block">
        <!-- Poster -->
        <div class="relative aspect-[2/3] overflow-hidden">
            <img src="{{ $movie['poster_url'] ?? 'https://via.placeholder.com/300x450/1f2937/6b7280?text=No+Image' }}"
                alt="{{ $movie['title'] }}"
                class="w-full h-full object-cover group-hover:scale-105 transition duration-500">

            <!-- Overlay -->
            <div class="movie-card-overlay absolute inset-0 opacity-0 group-hover:opacity-100 transition duration-300">
                <div class="absolute bottom-4 left-4 right-4">
                    <span class="btn-primary inline-block px-4 py-2 rounded-lg text-white text-sm font-medium">
                        Đặt Vé Ngay
                    </span>
                </div>
            </div>

            <!-- Badge -->
            @if(isset($movie['is_now_showing']) && $movie['is_now_showing'])
                <div class="absolute top-3 left-3">
                    <span class="bg-red-600 text-white text-xs font-medium px-2 py-1 rounded">
                        Đang Chiếu
                    </span>
                </div>
            @elseif(isset($movie['release_date']) && strtotime($movie['release_date']) > time())
                <div class="absolute top-3 left-3">
                    <span class="bg-blue-600 text-white text-xs font-medium px-2 py-1 rounded">
                        Sắp Chiếu
                    </span>
                </div>
            @endif

            <!-- Rating Badge (Age Classification) -->
            @if(isset($movie['rating']) && $movie['rating'])
                <div class="absolute top-3 right-3 bg-black/70 backdrop-blur-sm rounded-lg px-2 py-1">
                    <span class="text-yellow-400 text-xs font-bold">{{ $movie['rating'] }}</span>
                </div>
            @endif
        </div>

        <!-- Info -->
        <div class="p-4">
            <h3 class="text-white font-semibold text-lg mb-1 truncate group-hover:text-red-400 transition">
                {{ $movie['title'] }}
            </h3>

            <div class="flex items-center text-gray-400 text-sm space-x-3">
                @if(isset($movie['duration']))
                    <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ $movie['duration'] }} phút
                    </span>
                @endif

                @if(isset($movie['genre']))
                    <span
                        class="truncate">{{ is_array($movie['genre']) ? implode(', ', $movie['genre']) : $movie['genre'] }}</span>
                @endif
            </div>
        </div>
    </a>
</div>