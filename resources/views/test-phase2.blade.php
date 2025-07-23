<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phase 2 Component Testing</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-8">Phase 2 Component Testing</h1>
        
        <div class="space-y-8">
            <!-- Action Buttons Testing -->
            <section>
                <h2 class="text-2xl font-semibold mb-4">Action Buttons Component</h2>
                
                <div class="bg-white p-6 rounded-lg shadow space-y-6">
                    <!-- Basic Link Actions -->
                    <div>
                        <h3 class="font-medium mb-2">Basic Link Actions</h3>
                        <x-action-buttons :actions="[
                            ['type' => 'link', 'route' => 'dashboard', 'label' => 'View', 'color' => 'primary'],
                            ['type' => 'link', 'href' => '#', 'label' => 'Edit', 'color' => 'info'],
                            ['type' => 'link', 'href' => '#', 'label' => 'Delete', 'color' => 'danger']
                        ]" />
                    </div>
                    
                    <!-- Button Actions -->
                    <div>
                        <h3 class="font-medium mb-2">Button Actions</h3>
                        <x-action-buttons :actions="[
                            ['type' => 'button', 'label' => 'Save', 'color' => 'success', 'onclick' => 'alert(\"Save clicked!\")'],
                            ['type' => 'button', 'label' => 'Cancel', 'color' => 'secondary'],
                            ['type' => 'button', 'label' => 'Disabled', 'color' => 'gray', 'disabled' => true]
                        ]" />
                    </div>
                    
                    <!-- Form Actions -->
                    <div>
                        <h3 class="font-medium mb-2">Form Actions (with confirmation)</h3>
                        <x-action-buttons :actions="[
                            ['type' => 'link', 'href' => '#', 'label' => 'View Details', 'color' => 'primary'],
                            ['type' => 'delete', 'action' => '#', 'label' => 'Delete Item', 'color' => 'danger', 'confirm' => 'Are you sure you want to delete this item?']
                        ]" />
                    </div>
                    
                    <!-- With Icons -->
                    <div>
                        <h3 class="font-medium mb-2">Actions with Icons</h3>
                        <x-action-buttons :actions="[
                            ['type' => 'link', 'href' => '#', 'label' => 'Edit', 'color' => 'primary', 'icon' => 'm11 5l-7 7-7-7'],
                            ['type' => 'button', 'label' => 'Print', 'color' => 'info', 'icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z'],
                            ['type' => 'delete', 'action' => '#', 'label' => 'Remove', 'color' => 'danger', 'icon' => 'M6 18L18 6M6 6l12 12', 'confirm' => 'Delete this?']
                        ]" />
                    </div>
                    
                    <!-- Different Sizes -->
                    <div>
                        <h3 class="font-medium mb-2">Different Sizes</h3>
                        <div class="space-y-2">
                            <div>
                                <span class="text-sm text-gray-600 mr-4">Small:</span>
                                <x-action-buttons size="sm" :actions="[
                                    ['type' => 'link', 'href' => '#', 'label' => 'View', 'color' => 'primary'],
                                    ['type' => 'link', 'href' => '#', 'label' => 'Edit', 'color' => 'info']
                                ]" />
                            </div>
                            <div>
                                <span class="text-sm text-gray-600 mr-4">Default:</span>
                                <x-action-buttons :actions="[
                                    ['type' => 'link', 'href' => '#', 'label' => 'View', 'color' => 'primary'],
                                    ['type' => 'link', 'href' => '#', 'label' => 'Edit', 'color' => 'info']
                                ]" />
                            </div>
                            <div>
                                <span class="text-sm text-gray-600 mr-4">Large:</span>
                                <x-action-buttons size="lg" :actions="[
                                    ['type' => 'link', 'href' => '#', 'label' => 'View', 'color' => 'primary'],
                                    ['type' => 'link', 'href' => '#', 'label' => 'Edit', 'color' => 'info']
                                ]" />
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conditional Actions -->
                    <div>
                        <h3 class="font-medium mb-2">Conditional Actions</h3>
                        <x-action-buttons :actions="[
                            ['type' => 'link', 'href' => '#', 'label' => 'Always Visible', 'color' => 'primary'],
                            ['type' => 'link', 'href' => '#', 'label' => 'Conditionally Hidden', 'color' => 'info', 'when' => false],
                            ['type' => 'link', 'href' => '#', 'label' => 'Conditionally Shown', 'color' => 'success', 'when' => true]
                        ]" />
                    </div>
                </div>
            </section>
            
            <!-- Form Group Testing -->
            <section>
                <h2 class="text-2xl font-semibold mb-4">Form Group Component</h2>
                
                <div class="bg-white p-6 rounded-lg shadow space-y-6">
                    <form class="max-w-lg">
                        <!-- Text Input ---->
                        <x-form-group 
                            name="username" 
                            label="Username" 
                            type="text" 
                            placeholder="Enter your username"
                            required 
                            help="Username must be at least 3 characters long" />
                        
                        <!-- Email Input -->
                        <x-form-group 
                            name="email" 
                            label="Email Address" 
                            type="email" 
                            placeholder="Enter your email"
                            required />
                        
                        <!-- Password Input -->
                        <x-form-group 
                            name="password" 
                            label="Password" 
                            type="password" 
                            placeholder="Enter your password"
                            required />
                        
                        <!-- Textarea -->
                        <x-form-group 
                            name="description" 
                            label="Description" 
                            type="textarea" 
                            rows="4"
                            placeholder="Enter a description..."
                            help="Maximum 500 characters" />
                        
                        <!-- Select Dropdown -->
                        <x-form-group 
                            name="category" 
                            label="Category" 
                            type="select" 
                            placeholder="Choose a category..."
                            :options="[
                                'electronics' => 'Electronics',
                                'clothing' => 'Clothing',
                                'food' => 'Food & Beverage',
                                'books' => 'Books'
                            ]"
                            required />
                        
                        <!-- Checkbox -->
                        <x-form-group 
                            name="agree_terms" 
                            label="I agree to the terms and conditions" 
                            type="checkbox" 
                            required />
                        
                        <!-- Radio Buttons -->
                        <x-form-group 
                            name="priority" 
                            label="Priority Level" 
                            type="radio" 
                            :options="[
                                'low' => 'Low Priority',
                                'medium' => 'Medium Priority',
                                'high' => 'High Priority'
                            ]"
                            required />
                        
                        <!-- Number Input -->
                        <x-form-group 
                            name="quantity" 
                            label="Quantity" 
                            type="number" 
                            min="1"
                            max="100"
                            value="1" />
                        
                        <!-- Date Input -->
                        <x-form-group 
                            name="delivery_date" 
                            label="Delivery Date" 
                            type="date" 
                            required />
                        
                        <!-- Disabled Field -->
                        <x-form-group 
                            name="readonly_field" 
                            label="Read-only Field" 
                            type="text" 
                            value="This field is disabled"
                            disabled />
                        
                        <div class="flex justify-end space-x-2 pt-4">
                            <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                Submit Form
                            </button>
                        </div>
                    </form>
                </div>
            </section>
            
            <!-- Product Image Testing -->
            <section>
                <h2 class="text-2xl font-semibold mb-4">Product Image Component</h2>
                
                <div class="bg-white p-6 rounded-lg shadow space-y-6">
                    <!-- Different Sizes -->
                    <div>
                        <h3 class="font-medium mb-4">Different Sizes</h3>
                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <x-product-image size="xs" :product="(object)['NAME' => 'Test Product']" />
                                <span class="block text-xs text-gray-500 mt-1">XS</span>
                            </div>
                            <div class="text-center">
                                <x-product-image size="sm" :product="(object)['NAME' => 'Test Product']" />
                                <span class="block text-xs text-gray-500 mt-1">SM</span>
                            </div>
                            <div class="text-center">
                                <x-product-image size="md" :product="(object)['NAME' => 'Test Product']" />
                                <span class="block text-xs text-gray-500 mt-1">MD</span>
                            </div>
                            <div class="text-center">
                                <x-product-image size="lg" :product="(object)['NAME' => 'Test Product']" />
                                <span class="block text-xs text-gray-500 mt-1">LG</span>
                            </div>
                            <div class="text-center">
                                <x-product-image size="xl" :product="(object)['NAME' => 'Test Product']" />
                                <span class="block text-xs text-gray-500 mt-1">XL</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Style Variations -->
                    <div>
                        <h3 class="font-medium mb-4">Style Variations</h3>
                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <x-product-image :product="(object)['NAME' => 'Test Product']" :rounded="true" :border="true" />
                                <span class="block text-xs text-gray-500 mt-1">Default</span>
                            </div>
                            <div class="text-center">
                                <x-product-image :product="(object)['NAME' => 'Test Product']" :rounded="false" :border="true" />
                                <span class="block text-xs text-gray-500 mt-1">Square</span>
                            </div>
                            <div class="text-center">
                                <x-product-image :product="(object)['NAME' => 'Test Product']" :rounded="true" :border="false" />
                                <span class="block text-xs text-gray-500 mt-1">No Border</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fallback Behavior -->
                    <div>
                        <h3 class="font-medium mb-4">Fallback Behavior</h3>
                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <x-product-image :product="(object)['NAME' => 'No Image Product']" :fallback="true" />
                                <span class="block text-xs text-gray-500 mt-1">With Fallback</span>
                            </div>
                            <div class="text-center">
                                <x-product-image :product="(object)['NAME' => 'No Image Product']" :fallback="false" />
                                <span class="block text-xs text-gray-500 mt-1">No Fallback</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Product Image with Mock Supplier Service -->
                    <div>
                        <h3 class="font-medium mb-4">With Mock Image URL</h3>
                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <x-product-image 
                                    :product="(object)['NAME' => 'Mock Product', 'image_url' => 'https://via.placeholder.com/150x150?text=Product']" 
                                    size="lg" />
                                <span class="block text-xs text-gray-500 mt-1">Mock Image</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</body>
</html>