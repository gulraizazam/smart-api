@extends('admin.layouts.master')
@section('title', 'Feedback Report')
@section('content')

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Feedback Report'])

   <div class="container mx-auto p-6">
    <h2 class="text-2xl font-semibold mb-4">Doctor Feedback Report</h2>

    <div class="space-y-4">
        @foreach($feedbackData as $service)
        <div class="bg-white shadow rounded-lg border-l-4" style="border-color: {{ $service['color'] }};">
            <button class="w-full px-6 py-4 flex justify-between items-center text-left toggle-btn focus:outline-none">
                <div class="text-lg font-medium text-blue-700 hover:underline">
                    {{ $service['name'] }} (Avg: {{ $service['avg_rating'] }})
                </div>
                <svg class="h-5 w-5 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div class="toggle-content hidden px-6 py-4 bg-gray-50">
                @if(count($service['treatments']) > 0)
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($service['treatments'] as $treatment)
                        <li>
                            <span class="font-semibold text-gray-700">{{ $treatment['name'] }}</span>
                            <span class="text-sm text-gray-500">(Avg: {{ $treatment['avg_rating'] }})</span>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-gray-500 italic">No rated treatments.</p>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
</div>
</div>

<!-- Bootstrap JS for collapse functionality -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.toggle-btn').forEach(button => {
        button.addEventListener('click', () => {
            const content = button.nextElementSibling;
            const icon = button.querySelector('svg');

            const isOpen = !content.classList.contains('hidden');

            // Close all others (optional if you want only one open at a time)
            document.querySelectorAll('.toggle-content').forEach(c => c.classList.add('hidden'));
            document.querySelectorAll('.toggle-btn svg').forEach(i => i.classList.remove('rotate-180'));

            // Toggle current
            if (!isOpen) {
                content.classList.remove('hidden');
                icon.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                icon.classList.remove('rotate-180');
            }
        });
    });
</script>
@endsection
