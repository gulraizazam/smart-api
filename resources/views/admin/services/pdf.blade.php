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
            margin: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .services-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .services-table th {
            background-color: #333;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #333;
        }
        
        .services-table th.duration-header {
            text-align: center;
            width: 120px;
        }
        
        .services-table th.price-header {
            text-align: right;
            width: 100px;
        }
        
        .services-table td {
            padding: 8px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        
        .services-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .services-table tr:hover {
            background-color: #f5f5f5;
        }
        
        /* Parent service styling */
        .parent-row {
            background-color: #e8f4fd !important;
            font-weight: bold;
            border-left: 4px solid #007bff;
        }
        
        .parent-row td {
            font-weight: bold;
            color: #333;
            border-left: 4px solid #007bff;
        }
        
        /* Child service styling */
        .child-row {
            background-color: #fff;
        }
        
        .child-name {
            padding-left: 30px;
            position: relative;
        }
        
        .child-name:before {
            content: "└─ ";
            position: absolute;
            left: 8px;
            color: #666;
        }
        
        /* Nested children styling */
        .child-level-2 .child-name {
            padding-left: 50px;
        }
        
        .child-level-2 .child-name:before {
            left: 28px;
            content: "└─ ";
        }
        
        .child-level-3 .child-name {
            padding-left: 70px;
        }
        
        .child-level-3 .child-name:before {
            left: 48px;
            content: "└─ ";
        }
        
        .duration-cell {
            text-align: center;
            width: 120px;
        }
        
        .price-cell {
            text-align: right;
            font-weight: bold;
            width: 100px;
        }
        
        .name-cell {
            min-width: 200px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Services Tree Structure</h1>
        <p>Generated on: {{ date('F j, Y, g:i a') }}</p>
    </div>

    <table class="services-table">
        <thead>
            <tr>
                <th class="name-header">Service Name</th>
                <th class="duration-header">Duration</th>
                <th class="price-header">Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach($services as $parentService)
                <!-- Parent Service Row -->
                <tr class="parent-row">
                    <td class="name-cell">{{ $parentService->name }}</td>
                    <td class="duration-cell">{{ $parentService->duration }} min</td>
                    <td class="price-cell">{{ number_format($parentService->price, 2) }}</td>
                </tr>

                <!-- Child Services -->
                @if($parentService->children->count() > 0)
                    @foreach($parentService->children as $childService)
                        <tr class="child-row">
                            <td class="name-cell">
                                <div class="child-name">{{ $childService->name }}</div>
                            </td>
                            <td class="duration-cell">{{ $childService->duration }} min</td>
                            <td class="price-cell">{{ number_format($childService->price, 2) }}</td>
                        </tr>

                        {{-- Handle nested children if you have more than 2 levels --}}
                        @if($childService->children->count() > 0)
                            @foreach($childService->children as $grandchildService)
                                <tr class="child-row child-level-2">
                                    <td class="name-cell">
                                        <div class="child-name">{{ $grandchildService->name }}</div>
                                    </td>
                                    <td class="duration-cell">{{ $grandchildService->duration }} min</td>
                                    <td class="price-cell">{{ number_format($grandchildService->price, 2) }}</td>
                                </tr>

                                {{-- Third level nesting --}}
                                @if($grandchildService->children->count() > 0)
                                    @foreach($grandchildService->children as $greatGrandchildService)
                                        <tr class="child-row child-level-3">
                                            <td class="name-cell">
                                                <div class="child-name">{{ $greatGrandchildService->name }}</div>
                                            </td>
                                            <td class="duration-cell">{{ $greatGrandchildService->duration }} min</td>
                                            <td class="price-cell">{{ number_format($greatGrandchildService->price, 2) }}</td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>