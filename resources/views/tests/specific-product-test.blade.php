<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDEA Specific Product Test - {{ $results['product_code'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; }
        .section { background: white; margin: 20px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        h1, h2, h3 { margin-top: 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; max-height: 300px; overflow-y: auto; }
        .status-good { color: #28a745; font-weight: bold; }
        .status-bad { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px 12px; text-align: left; border: 1px solid #ddd; word-break: break-word; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .method-item { margin: 15px 0; padding: 15px; border: 2px solid #ddd; border-radius: 8px; }
        .method-success { border-color: #28a745; background-color: #f8fff9; }
        .method-partial { border-color: #ffc107; background-color: #fffdf0; }
        .method-failed { border-color: #dc3545; background-color: #fff5f5; }
        .url-list { max-height: 200px; overflow-y: auto; }
        .comparison-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .highlight { background-color: #ffffcc; padding: 2px 4px; border-radius: 3px; }
        .english-link { color: #28a745; font-weight: bold; }
        .dutch-link { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 UDEA Product {{ $results['product_code'] }} Language Test</h1>
        
        <div class="section info">
            <h2>Test Overview</h2>
            <p>Testing multiple session setup methods to find the correct way to get English product names for product <strong>{{ $results['product_code'] }}</strong> which has both English and Dutch versions available.</p>
            <table>
                <tr><th>Product Code</th><td>{{ $results['product_code'] }}</td></tr>
                <tr><th>Test Time</th><td>{{ $results['timestamp'] }}</td></tr>
                <tr><th>Objective</th><td>Find session method that returns English product links instead of Dutch ones</td></tr>
            </table>
        </div>

        @if(isset($results['error']))
            <div class="section error">
                <h2>❌ Global Error</h2>
                <p>{{ $results['error'] }}</p>
            </div>
        @endif

        {{-- Baseline Test --}}
        @if(isset($results['baseline_test']))
            <div class="section {{ $results['baseline_test']['success'] ? 'success' : 'error' }}">
                <h2>📊 Baseline Test (Current Working Method)</h2>
                @php $baseline = $results['baseline_test']; @endphp
                
                <table>
                    <tr>
                        <th>Status</th>
                        <td class="{{ $baseline['success'] ? 'status-good' : 'status-bad' }}">
                            {{ $baseline['success'] ? 'SUCCESS' : 'FAILED' }}
                        </td>
                    </tr>
                    @if($baseline['success'])
                        <tr>
                            <th>Total Product Links</th>
                            <td class="{{ count($baseline['product_links']) > 0 ? 'status-good' : 'status-warning' }}">
                                {{ count($baseline['product_links']) }}
                            </td>
                        </tr>
                        @if(count($baseline['product_links']) > 0)
                            <tr>
                                <th>Product Links Found</th>
                                <td class="url-list">
                                    @foreach($baseline['product_links'] as $link)
                                        <div class="{{ strpos($link, '/products/') !== false ? 'english-link' : 'dutch-link' }}">
                                            {{ strpos($link, '/products/') !== false ? '🇬🇧' : '🇳🇱' }} {{ $link }}
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                        
                        @if(isset($baseline['product_details']))
                            @php $details = $baseline['product_details']; @endphp
                            <tr>
                                <th>Product Details</th>
                                <td>
                                    @if($details['success'])
                                        <strong>URL:</strong> <span class="{{ $details['is_english'] ? 'english-link' : 'dutch-link' }}">{{ $details['url'] }}</span><br>
                                        <strong>Language:</strong> {{ $details['is_english'] ? '🇬🇧 English' : '🇳🇱 Dutch' }}<br>
                                        @if($details['full_description'])
                                            <strong>Product Name:</strong> <span class="highlight">{{ $details['full_description'] }}</span><br>
                                        @endif
                                        @if($details['product_name'])
                                            <strong>Title:</strong> {{ $details['product_name'] }}<br>
                                        @endif
                                        @if($details['brand'])
                                            <strong>Brand:</strong> {{ $details['brand'] }}<br>
                                        @endif
                                        @if($details['size'])
                                            <strong>Size:</strong> {{ $details['size'] }}
                                        @endif
                                    @else
                                        <span class="status-bad">Failed to get product details</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                        
                        @if(isset($baseline['search_html_sample']))
                            <tr>
                                <th>Search HTML Sample</th>
                                <td><pre>{{ $baseline['search_html_sample'] }}</pre></td>
                            </tr>
                        @endif
                    @endif
                    @if(isset($baseline['error']))
                        <tr><th>Error</th><td class="status-bad">{{ $baseline['error'] }}</td></tr>
                    @endif
                </table>
            </div>
        @endif

        {{-- Session Experiments --}}
        @if(isset($results['session_experiments']))
            <div class="section">
                <h2>🧪 Session Setup Experiments</h2>
                <p>Testing {{ count($results['session_experiments']) }} different methods to establish English language preference before searching.</p>
                
                @foreach($results['session_experiments'] as $index => $experiment)
                    @php
                        $status_class = 'method-failed';
                        if (($experiment['english_links_found'] ?? 0) > 0) {
                            $status_class = 'method-success';
                        } elseif ($experiment['success']) {
                            $status_class = 'method-partial';
                        }
                    @endphp
                    
                    <div class="method-item {{ $status_class }}">
                        <h3>Method {{ $index + 1 }}: {{ $experiment['method_name'] }}</h3>
                        
                        <table>
                            <tr>
                                <th>Status</th>
                                <td class="{{ $experiment['success'] ? 'status-good' : 'status-bad' }}">
                                    {{ $experiment['success'] ? 'SUCCESS' : 'FAILED' }}
                                </td>
                            </tr>
                            @if($experiment['success'])
                                <tr>
                                    <th>Total Links Found</th>
                                    <td>{{ count($experiment['product_links']) }}</td>
                                </tr>
                                <tr>
                                    <th>English Links Found</th>
                                    <td class="{{ ($experiment['english_links_found'] ?? 0) > 0 ? 'status-good' : 'status-bad' }}">
                                        {{ $experiment['english_links_found'] ?? 0 }} 
                                        @if(($experiment['english_links_found'] ?? 0) > 0)
                                            🎉 SUCCESS!
                                        @endif
                                    </td>
                                </tr>
                                
                                @if(count($experiment['product_links']) > 0)
                                    <tr>
                                        <th>Product Links</th>
                                        <td class="url-list">
                                            @foreach($experiment['product_links'] as $link)
                                                <div class="{{ strpos($link, '/products/') !== false ? 'english-link' : 'dutch-link' }}">
                                                    {{ strpos($link, '/products/') !== false ? '🇬🇧' : '🇳🇱' }} {{ $link }}
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif
                                
                                @if(isset($experiment['product_details']) && $experiment['product_details']['success'])
                                    @php $details = $experiment['product_details']; @endphp
                                    <tr>
                                        <th>Product Details</th>
                                        <td>
                                            <strong>URL:</strong> <span class="{{ $details['is_english'] ? 'english-link' : 'dutch-link' }}">{{ $details['url'] }}</span><br>
                                            <strong>Language:</strong> {{ $details['is_english'] ? '🇬🇧 English' : '🇳🇱 Dutch' }}<br>
                                            @if($details['full_description'])
                                                <strong>Full Product Name:</strong> <span class="highlight">{{ $details['full_description'] }}</span><br>
                                            @endif
                                            @if($details['product_name'])
                                                <strong>Title:</strong> {{ $details['product_name'] }}<br>
                                            @endif
                                            @if($details['brand'])
                                                <strong>Brand:</strong> {{ $details['brand'] }}<br>
                                            @endif
                                            @if($details['size'])
                                                <strong>Size:</strong> {{ $details['size'] }}
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                                
                                @if(isset($experiment['search_html_sample']))
                                    <tr>
                                        <th>Search HTML Sample</th>
                                        <td><pre>{{ $experiment['search_html_sample'] }}</pre></td>
                                    </tr>
                                @endif
                            @endif
                            @if(isset($experiment['error']))
                                <tr><th>Error</th><td class="status-bad">{{ $experiment['error'] }}</td></tr>
                            @endif
                        </table>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Comparison Results --}}
        @if(isset($results['comparison']))
            <div class="section {{ (isset($results['comparison']['best_method']) && $results['comparison']['best_method']) ? 'success' : 'warning' }}">
                <h2>🏆 Results Comparison</h2>
                @php $comparison = $results['comparison']; @endphp
                
                <div class="comparison-grid">
                    <div>
                        <h3>Baseline Performance</h3>
                        <table>
                            <tr>
                                <th>Baseline Success</th>
                                <td class="{{ $comparison['baseline_success'] ? 'status-good' : 'status-bad' }}">
                                    {{ $comparison['baseline_success'] ? 'YES' : 'NO' }}
                                </td>
                            </tr>
                            <tr>
                                <th>Total Links</th>
                                <td>{{ $comparison['baseline_links_count'] }}</td>
                            </tr>
                            <tr>
                                <th>English Links</th>
                                <td class="{{ $comparison['baseline_english_links'] > 0 ? 'status-good' : 'status-bad' }}">
                                    {{ $comparison['baseline_english_links'] }}
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div>
                        <h3>Experiment Results</h3>
                        <table>
                            <tr>
                                <th>Successful Methods</th>
                                <td class="{{ count($comparison['successful_experiments']) > 0 ? 'status-good' : 'status-bad' }}">
                                    {{ count($comparison['successful_experiments']) }}
                                </td>
                            </tr>
                            @if(count($comparison['successful_experiments']) > 0)
                                <tr>
                                    <th>Methods Found</th>
                                    <td>
                                        @foreach($comparison['successful_experiments'] as $method)
                                            <div class="status-good">✓ {{ $method }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @endif
                            @if($comparison['best_method'])
                                <tr>
                                    <th>Best Method</th>
                                    <td class="status-good">
                                        <strong>{{ $comparison['best_method']['name'] }}</strong><br>
                                        English Links: {{ $comparison['best_method']['english_links'] }}
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>
                
                @if($comparison['best_method'] && isset($comparison['best_method']['product_details']))
                    <h3>🎯 Best Method Product Details</h3>
                    @php $best_details = $comparison['best_method']['product_details']; @endphp
                    @if($best_details && $best_details['success'])
                        <div class="highlight" style="padding: 15px; font-size: 16px;">
                            <strong>Product Name Found:</strong> {{ $best_details['full_description'] ?? 'N/A' }}<br>
                            <strong>Language:</strong> {{ $best_details['is_english'] ? '🇬🇧 English' : '🇳🇱 Dutch' }}<br>
                            <strong>URL Pattern:</strong> {{ $best_details['is_english'] ? '/products/product/' : '/producten/product/' }}
                        </div>
                    @endif
                @endif
            </div>
        @endif

        <div class="section info">
            <h2>🎯 Implementation Guidance</h2>
            
            @if(isset($results['comparison']['best_method']) && $results['comparison']['best_method'])
                <div class="success" style="padding: 15px; margin: 10px 0;">
                    <h3>🎉 SUCCESS - English Method Found!</h3>
                    <p><strong>Best Method:</strong> {{ $results['comparison']['best_method']['name'] }}</p>
                    <p>This method successfully returned {{ $results['comparison']['best_method']['english_links'] }} English product link(s). 
                    The method should be implemented in the main UdeaScrapingService to get English product names.</p>
                </div>
            @elseif(isset($results['comparison']['successful_experiments']) && count($results['comparison']['successful_experiments']) > 0)
                <div class="warning" style="padding: 15px; margin: 10px 0;">
                    <h3>⚠️ Partial Success</h3>
                    <p>{{ count($results['comparison']['successful_experiments']) }} method(s) worked but may not have returned English links. 
                    Further analysis needed to determine if UDEA has English versions for this product.</p>
                </div>
            @else
                <div class="info" style="padding: 15px; margin: 10px 0;">
                    <h3>🔍 No English Links Found</h3>
                    <p>None of the tested methods successfully returned English product links. This could mean:</p>
                    <ul>
                        <li>UDEA doesn't have English versions of this specific product</li>
                        <li>The English language switching mechanism is more complex</li>
                        <li>Additional authentication or session setup is required</li>
                    </ul>
                </div>
            @endif

            <h3>Next Steps:</h3>
            <ul>
                @if(isset($results['comparison']['best_method']) && $results['comparison']['best_method'])
                    <li><strong>Implement the successful method</strong> in UdeaScrapingService.php</li>
                    <li>Test with other product codes to ensure consistency</li>
                    <li>Update the main scraping workflow to use English links when available</li>
                @else
                    <li>Test with different product codes that are known to have English versions</li>
                    <li>Analyze the UDEA website's client-side JavaScript for language switching</li>
                    <li>Consider that some products may only have Dutch names available</li>
                @endif
            </ul>
        </div>

        <div class="section info">
            <h2>Quick Test Links</h2>
            <ul>
                <li><a href="?product_code=6001223">Test Product 6001223 (Current)</a></li>
                <li><a href="?product_code=115">Test Product 115 (Broccoli)</a></li>
                <li><a href="?product_code=2192">Test Product 2192 (Ice cream)</a></li>
                <li><a href="?product_code=161">Test Product 161 (Tomatoes)</a></li>
            </ul>
        </div>
    </div>
</body>
</html>