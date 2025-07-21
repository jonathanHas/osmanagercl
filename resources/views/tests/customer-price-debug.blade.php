<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Price Debug - {{ $debugInfo['product_code'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .section { background: white; margin: 20px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        h1, h2, h3 { margin-top: 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        .highlight { background-color: yellow; }
        .status-good { color: #28a745; font-weight: bold; }
        .status-bad { color: #dc3545; font-weight: bold; }
        .json-data { max-height: 400px; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px 12px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .code-block { font-family: 'Courier New', monospace; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Customer Price Extraction Debug</h1>
        
        <div class="section info">
            <h2>Test Configuration</h2>
            <table>
                <tr><th>Product Code</th><td>{{ $debugInfo['product_code'] }}</td></tr>
                <tr><th>Timestamp</th><td>{{ $debugInfo['timestamp'] }}</td></tr>
                <tr><th>Search URL</th><td><a href="{{ $debugInfo['search_url'] }}" target="_blank">{{ $debugInfo['search_url'] }}</a></td></tr>
                @if(isset($debugInfo['search_response_code']))
                    <tr><th>Search Response Code</th><td>{{ $debugInfo['search_response_code'] }}</td></tr>
                @endif
                @if(isset($debugInfo['search_title']))
                    <tr><th>Search Page Title</th><td>{{ $debugInfo['search_title'] }}</td></tr>
                @endif
                @if(isset($debugInfo['search_contains_results']))
                    <tr><th>Contains Search Results</th><td class="{{ $debugInfo['search_contains_results'] ? 'status-good' : 'status-bad' }}">{{ $debugInfo['search_contains_results'] ? 'YES' : 'NO' }}</td></tr>
                @endif
                @if(isset($debugInfo['dutch_detected']))
                    <tr><th>Dutch Version Detected</th><td class="{{ $debugInfo['dutch_detected'] ? 'status-good' : 'status-bad' }}">{{ $debugInfo['dutch_detected'] ? 'YES' : 'NO' }}</td></tr>
                @endif
                @if(isset($debugInfo['english_detected']))
                    <tr><th>English Version Detected</th><td class="{{ $debugInfo['english_detected'] ? 'status-good' : 'status-bad' }}">{{ $debugInfo['english_detected'] ? 'YES' : 'NO' }}</td></tr>
                @endif
            </table>
        </div>

        @if(!empty($debugInfo['errors']))
            <div class="section error">
                <h2>❌ Errors Found</h2>
                <ul>
                    @foreach($debugInfo['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="section {{ isset($debugInfo['products_list_found']) && $debugInfo['products_list_found'] ? 'success' : 'error' }}">
            <h2>Step 1a: Products List Section</h2>
            <table>
                @if(isset($debugInfo['products_list_found']))
                    <tr><th>Products List Found</th><td class="{{ $debugInfo['products_list_found'] ? 'status-good' : 'status-bad' }}">{{ $debugInfo['products_list_found'] ? 'YES' : 'NO' }}</td></tr>
                @endif
                @if(isset($debugInfo['all_div_ids']))
                    <tr><th>All Div IDs Found</th><td>{{ implode(', ', $debugInfo['all_div_ids']) }}</td></tr>
                @endif
            </table>
            
            @if(isset($debugInfo['products_list_preview']))
                <h3>🔍 Products List HTML Preview</h3>
                <div class="code-block">
                    <pre>{{ $debugInfo['products_list_preview'] }}</pre>
                </div>
            @endif
        </div>

        <div class="section {{ $debugInfo['detail_url_found'] ? 'success' : 'error' }}">
            <h2>Step 1b: Detail URL Extraction</h2>
            <table>
                <tr><th>Detail URL Found</th><td class="{{ $debugInfo['detail_url_found'] ? 'status-good' : 'status-bad' }}">{{ $debugInfo['detail_url_found'] ? 'YES' : 'NO' }}</td></tr>
                @if($debugInfo['detail_url_found'])
                    <tr><th>Detail URL</th><td><a href="{{ $debugInfo['detail_url'] }}" target="_blank">{{ $debugInfo['detail_url'] }}</a></td></tr>
                @endif
            </table>

            @if(!$debugInfo['detail_url_found'] && isset($debugInfo['all_links_in_products_list']))
                <h3>🔍 Debug: All Links Found in Products List</h3>
                <div class="code-block">
                    @foreach($debugInfo['all_links_in_products_list'] as $link)
                        <div>{{ $link }}</div>
                    @endforeach
                </div>
            @endif

            @if(!$debugInfo['detail_url_found'] && isset($debugInfo['all_a_tags_in_products_list']))
                <h3>🔍 Debug: All A Tags in Products List</h3>
                <div class="code-block">
                    @foreach($debugInfo['all_a_tags_in_products_list'] as $tag)
                        <div>{{ $tag }}</div>
                    @endforeach
                </div>
            @endif

            @if(!$debugInfo['detail_url_found'] && isset($debugInfo['product_links_found']))
                <h3>🔍 Debug: Product Links Found</h3>
                <div class="code-block">
                    @foreach($debugInfo['product_links_found'] as $link)
                        <div>{{ $link }}</div>
                    @endforeach
                </div>
            @endif

            @if(!$debugInfo['detail_url_found'] && isset($debugInfo['all_links_found']))
                <h3>🔍 Debug: All Links Found in Search Results</h3>
                <div class="code-block">
                    @foreach($debugInfo['all_links_found'] as $link)
                        <div>{{ $link }}</div>
                    @endforeach
                </div>
            @endif

            @if(!$debugInfo['detail_url_found'] && isset($debugInfo['detail_image_classes']))
                <h3>🔍 Debug: Detail Image Classes Found</h3>
                <div class="code-block">
                    @foreach($debugInfo['detail_image_classes'] as $class)
                        <div>{{ $class }}</div>
                    @endforeach
                </div>
            @endif
        </div>

        @if($debugInfo['detail_url_found'])
            <div class="section {{ $debugInfo['customer_price_found'] ? 'success' : 'warning' }}">
                <h2>Step 2: Customer Price Extraction</h2>
                <table>
                    <tr><th>Customer Price Found</th><td class="{{ $debugInfo['customer_price_found'] ? 'status-good' : 'status-bad' }}">{{ $debugInfo['customer_price_found'] ? 'YES' : 'NO' }}</td></tr>
                    @if($debugInfo['customer_price_found'])
                        <tr><th>Customer Price</th><td class="status-good">€{{ $debugInfo['customer_price'] }}</td></tr>
                    @endif
                </table>

                @if(!$debugInfo['customer_price_found'] && isset($debugInfo['customer_mentions']))
                    <h3>🔍 Debug: All Customer/Klant Mentions Found</h3>
                    <div class="code-block">
                        @foreach($debugInfo['customer_mentions'] as $mention)
                            <div>{{ $mention }}</div>
                        @endforeach
                    </div>
                @endif

                @if(!$debugInfo['customer_price_found'] && isset($debugInfo['context_around_289']))
                    <h3>🔍 Debug: Context Around Price 2,89</h3>
                    <div class="code-block">
                        @foreach($debugInfo['context_around_289'] as $context)
                            <div style="margin: 5px 0; padding: 5px; background: #f0f0f0;">{{ $context }}</div>
                        @endforeach
                    </div>
                @endif

                @if(!$debugInfo['customer_price_found'] && isset($debugInfo['all_prices_on_detail_page']))
                    <h3>🔍 Debug: All Prices Found on Detail Page</h3>
                    <div class="code-block">
                        @foreach($debugInfo['all_prices_on_detail_page'] as $price)
                            <div>{{ $price }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if($debugInfo['detail_url_found'] && isset($debugInfo['barcode_extraction']))
            <div class="section {{ $debugInfo['barcode_extraction']['barcode_found'] ? 'success' : 'warning' }}">
                <h2>Step 3: Barcode/EAN Extraction</h2>
                <table>
                    <tr><th>Barcode Found</th><td class="{{ $debugInfo['barcode_extraction']['barcode_found'] ? 'status-good' : 'status-bad' }}">{{ $debugInfo['barcode_extraction']['barcode_found'] ? 'YES' : 'NO' }}</td></tr>
                    @if($debugInfo['barcode_extraction']['barcode_found'])
                        <tr><th>Barcode Value</th><td class="status-good">{{ $debugInfo['barcode_extraction']['barcode_value'] }}</td></tr>
                        <tr><th>Pattern Used</th><td class="status-good">{{ $debugInfo['barcode_extraction']['pattern_used'] }}</td></tr>
                    @endif
                </table>

                <h3>🔍 Barcode Extraction Patterns Tested</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Pattern Name</th>
                            <th>Found</th>
                            <th>Regular Expression</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($debugInfo['barcode_extraction']['patterns_tested'] as $pattern)
                            <tr class="{{ $pattern['found'] ? 'status-good' : '' }}">
                                <td>{{ $pattern['name'] }}</td>
                                <td class="{{ $pattern['found'] ? 'status-good' : 'status-bad' }}">{{ $pattern['found'] ? 'MATCH' : 'NO MATCH' }}</td>
                                <td class="code-block" style="font-size: 10px; word-break: break-all;">{{ $pattern['pattern'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if(isset($debugInfo['barcode_extraction']['all_ean_mentions']) && !empty($debugInfo['barcode_extraction']['all_ean_mentions']))
                    <h3>🔍 Debug: All EAN/Barcode Mentions Found</h3>
                    <div class="code-block">
                        @foreach($debugInfo['barcode_extraction']['all_ean_mentions'] as $mention)
                            <div style="margin: 3px 0; padding: 3px; background: #f0f0f0; border-left: 3px solid #007bff;">{{ $mention }}</div>
                        @endforeach
                    </div>
                @endif

                @if(isset($debugInfo['barcode_extraction']['ean_table_rows']) && !empty($debugInfo['barcode_extraction']['ean_table_rows']))
                    <h3>🔍 Debug: EAN Table Rows Found</h3>
                    <div class="code-block">
                        @foreach($debugInfo['barcode_extraction']['ean_table_rows'] as $row)
                            <div style="margin: 5px 0; padding: 5px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 3px;">{{ htmlspecialchars($row) }}</div>
                        @endforeach
                    </div>
                @endif

                @if(!$debugInfo['barcode_extraction']['barcode_found'])
                    <h3>🔍 Debug: HTML Context Search</h3>
                    <p>Look for the EAN value <strong>8711521021925</strong> in the detail page HTML preview below to identify the correct pattern.</p>
                @endif
            </div>
        @endif

        <div class="section info">
            <h2>Complete Scraping Result</h2>
            @if($debugInfo['all_data'])
                <div class="json-data">
                    <pre>{{ json_encode($debugInfo['all_data'], JSON_PRETTY_PRINT) }}</pre>
                </div>
            @else
                <p class="status-bad">No data returned from scraping service</p>
            @endif
        </div>

        <div class="section">
            <h2>Search Results HTML Preview</h2>
            <div class="code-block">
                <pre>{{ $debugInfo['search_html_preview'] }}</pre>
            </div>
        </div>

        @if($debugInfo['detail_url_found'])
            <div class="section">
                <h2>Detail Page HTML Preview</h2>
                <div class="code-block">
                    <pre>{{ $debugInfo['detail_html_preview'] }}</pre>
                </div>
            </div>
        @endif

        <div class="section info">
            <h2>Quick Test Links</h2>
            <p>Test other products:</p>
            <ul>
                <li><a href="{{ route('tests.customer-price', '5014415') }}">Test Product 5014415</a></li>
                <li><a href="{{ route('tests.customer-price', '5002415') }}">Test Product 5002415</a></li>
                <li><a href="{{ route('tests.customer-price', '2192') }}">Test Product 2192 (Ice cream almond choc)</a></li>
            </ul>
        </div>
    </div>
</body>
</html>