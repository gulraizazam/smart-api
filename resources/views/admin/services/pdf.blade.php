<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Services Tree Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .service-parent {
            font-weight: bold;
            font-size: 14px;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #333;
            background-color: #f5f5f5;
            padding: 8px;
            border-left: 4px solid #007bff;
        }
        
        .service-child {
            margin-left: 20px;
            margin-bottom: 5px;
            padding: 5px 0;
            border-bottom: 1px dotted #ccc;
        }
        
        .service-child:last-child {
            border-bottom: none;
        }
        
        .service-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .service-name {
            flex: 1;
        }
        
        .service-duration {
            width: 100px;
            text-align: center;
        }
        
        .service-price {
            width: 80px;
            text-align: right;
            font-weight: bold;
        }
        
        .table-header {
            background-color: #333;
            color: white;
            padding: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }
        
        .table-header .header-name {
            flex: 1;
            font-weight: bold;
        }
        
        .table-header .header-duration {
            width: 100px;
            text-align: center;
            font-weight: bold;
        }
        
        .table-header .header-price {
            width: 80px;
            text-align: right;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Services Tree Structure</h1>
        <p>Generated on: {{ date('F j, Y, g:i a') }}</p>
    </div>

    <div class="table-header">
        <div class="header-name">Service Name</div>
        <div class="header-duration">Duration</div>
        <div class="header-price">Price</div>
    </div>

    @foreach($services as $parentService)
        <div class="service-parent">
            <div class="service-info">
                <div class="service-name">{{ $parentService->name }}</div>
                <div class="service-duration">{{ $parentService->duration }} min</div>
                <div class="service-price">${{ number_format($parentService->price, 2) }}</div>
            </div>
        </div>

        @if($parentService->children->count() > 0)
            @foreach($parentService->children as $childService)
                <div class="service-child">
                    <div class="service-info">
                        <div class="service-name">└─ {{ $childService->name }}</div>
                        <div class="service-duration">{{ $childService->duration }} min</div>
                        <div class="service-price">${{ number_format($childService->price, 2) }}</div>
                    </div>
                </div>

                {{-- Handle nested children if you have more than 2 levels --}}
                @if($childService->children->count() > 0)
                    @include('services.pdf-recursive', ['services' => $childService->children, 'level' => 2])
                @endif
            @endforeach
        @endif
    @endforeach
</body>
</html>