# Kitchen Recipe Costing System

The Kitchen Recipe Costing System provides comprehensive recipe management with ingredient costing, overhead calculations (labour, electricity, and packaging), profit margin analysis, and batch scaling tools.

## Overview

This system enables you to:
- Create and manage recipes with ingredients linked to POS products
- Define ingredient profiles for accurate unit conversions and costing
- Calculate total recipe costs including ingredients, labour, electricity, and packaging
- Analyze profit margins when recipes are linked to POS products
- Track cost history over time for trend analysis
- **Scale recipes** to see cost efficiencies when producing larger batches

## Features

### Recipe Management

Create recipes with:
- **Basic Info**: Name, description, prep time, cook time, portions produced
- **Linked Product**: Connect to POS product for automatic sell price and margin
- **Ingredients**: Add ingredients from POS products with quantities and units
- **Notes**: Additional preparation notes

### Ingredient Profiles

Define reusable ingredient costing profiles:
- **Purchase Unit**: How the product is purchased (kg, L, each, case)
- **Recipe Unit**: How it's measured in recipes (g, ml, tsp, cups)
- **Conversion Factor**: Units per purchase unit (e.g., 1000g per 1kg)
- **Density**: For weight↔volume conversions (e.g., flour = 0.593 g/ml)
- **Waste Factor**: Percentage lost during preparation
- **Delivery Markup**: Additional cost percentage for imported products

### Labour Cost Calculation

Labour costs are calculated automatically from recipe times:

```
labour_cost = (prep_time + cook_time) / 60 × labour_rate_per_hour
```

**Default Rate**: €15.00/hour (configurable via `KITCHEN_LABOUR_RATE` env variable)

### Electricity Cost Calculation

Electricity costs are calculated from cooking time:

```
electricity_cost = cook_time / 60 × cooking_power_kw × electricity_rate_per_kwh
```

**Default Rates**:
- Electricity: €0.25/kWh (`KITCHEN_ELECTRICITY_RATE`)
- Cooking Power: 2.0 kW (`KITCHEN_AVG_COOKING_POWER`)

### Per-Recipe Rate Overrides

Override global defaults for specific recipes:
- **Labour Rate Override**: Custom hourly rate for this recipe
- **Electricity Rate Override**: Custom €/kWh for this recipe
- **Cooking Power Override**: Custom kW for this recipe
- **Packaging Cost**: Cost per portion for containers, lids, labels, etc.

Use overrides for recipes with special requirements (e.g., commercial equipment, specialized labour, takeaway packaging).

### Packaging Cost

Some recipes require packaging (containers, lids, labels). Set the packaging cost per portion in the recipe overrides section:

```
packaging_total = packaging_cost_per_portion × portions_produced
```

This is automatically included in the total cost and cost-per-portion calculations.

### Cost Breakdown

The system displays a complete cost breakdown:
- **Ingredients**: Sum of all ingredient line costs
- **Labour**: Calculated from prep + cook time
- **Electricity**: Calculated from cook time
- **Packaging**: Per-portion packaging cost × portions (if set)
- **Total Cost**: Ingredients + Labour + Electricity + Packaging
- **Cost per Portion**: Total ÷ portions produced

### Margin Analysis

When linked to a POS product:
- **Sell Price**: From linked product
- **Profit per Portion**: Sell price - cost per portion
- **Margin Percentage**: (Profit ÷ Sell Price) × 100

**Margin Status Indicators**:
| Status | Margin | Color |
|--------|--------|-------|
| Excellent | 40%+ | Green |
| Good | 20-40% | Yellow |
| Low | 10-20% | Orange |
| Critical | <10% | Red |

### Batch Scaling Calculator

The scaling calculator helps analyze cost efficiencies when producing larger batches. Access it from the recipe edit page sidebar.

**Scaling Factors:**
- **Recipe Multiplier**: Scale ingredient quantities (2x, 3x, 5x, 10x, or custom)
- **Labour Factor**: Independent scaling for labour (e.g., 2x batch might only need 1.5x labour)
- **Electricity Factor**: Independent scaling for electricity (e.g., same oven time for larger batch)

**Smart Defaults:**
When you change the recipe multiplier, suggested factors are automatically set:
| Multiplier | Labour Factor | Electricity Factor |
|------------|---------------|-------------------|
| 2x | 1.5x | 1.0x |
| 3-5x | 2.0x | 1.5x |
| 10x+ | 3.0x | 2.0x |

**Features:**
- Real-time comparison table showing original vs scaled costs
- Per-portion cost comparison with savings percentage
- **Save as New Recipe**: Create a scaled version as a separate recipe

**Scaling Calculation:**
```
scaled_ingredients = original_ingredients × recipe_multiplier
scaled_labour = original_labour × labour_factor
scaled_electricity = original_electricity × electricity_factor
scaled_packaging = packaging_per_portion × (portions × recipe_multiplier)
scaled_total = scaled_ingredients + scaled_labour + scaled_electricity + scaled_packaging
scaled_portions = original_portions × recipe_multiplier
scaled_cost_per_portion = scaled_total / scaled_portions
savings_percent = ((original_cost_per_portion - scaled_cost_per_portion) / original_cost_per_portion) × 100
```

## Configuration

### Environment Variables

Add to your `.env` file:

```env
KITCHEN_LABOUR_RATE=15.00
KITCHEN_ELECTRICITY_RATE=0.25
KITCHEN_AVG_COOKING_POWER=2.0
```

### Config File

Located at `config/kitchen.php`:

```php
return [
    'labour_rate' => env('KITCHEN_LABOUR_RATE', 15.00),
    'electricity_rate' => env('KITCHEN_ELECTRICITY_RATE', 0.25),
    'avg_cooking_power' => env('KITCHEN_AVG_COOKING_POWER', 2.0),
];
```

## Database Schema

### kitchen_recipes

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | varchar(255) | Recipe name |
| description | text | Recipe description |
| prep_time | int | Preparation time in minutes |
| cook_time | int | Cooking time in minutes |
| portions_produced | int | Number of portions |
| pos_product_id | varchar | Linked POS product ID |
| is_active | boolean | Active status |
| notes | text | Additional notes |
| labour_rate_override | decimal(8,2) | Override labour rate |
| electricity_rate_override | decimal(8,4) | Override electricity rate |
| cooking_power_override | decimal(8,2) | Override cooking power |
| packaging_cost_per_portion | decimal(8,2) | Packaging cost per portion |
| created_at | timestamp | Created timestamp |
| updated_at | timestamp | Updated timestamp |

### kitchen_recipe_ingredients

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| recipe_id | bigint | Foreign key to recipes |
| pos_product_id | varchar | POS product used |
| ingredient_profile_id | bigint | Optional profile reference |
| quantity | decimal(10,4) | Amount used |
| unit_type | varchar | Unit of measurement |
| waste_factor | decimal(5,2) | Waste percentage |
| notes | varchar(255) | Ingredient notes |

### kitchen_ingredient_profiles

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| pos_product_id | varchar | POS product ID |
| name | varchar(255) | Profile name |
| purchase_unit | varchar | Unit when purchased |
| recipe_unit | varchar | Unit in recipes |
| units_per_purchase | decimal | Conversion factor |
| density | decimal | Weight/volume density |
| cost_per_recipe_unit | decimal | Calculated unit cost |
| apply_delivery_markup | boolean | Add delivery markup |
| delivery_markup_percent | decimal | Markup percentage |
| notes | text | Profile notes |

### kitchen_recipe_cost_history

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| recipe_id | bigint | Foreign key to recipes |
| total_cost | decimal | Total recipe cost |
| cost_per_portion | decimal | Per-portion cost |
| sell_price | decimal | Sell price at time |
| margin_percentage | decimal | Margin at time |
| recorded_at | timestamp | When recorded |

## API Endpoints

### Recipes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/kitchen` | List all recipes |
| GET | `/kitchen/create` | Show create form |
| POST | `/kitchen` | Store new recipe |
| GET | `/kitchen/{recipe}` | Show recipe details |
| GET | `/kitchen/{recipe}/edit` | Show edit form |
| PUT | `/kitchen/{recipe}` | Update recipe |
| DELETE | `/kitchen/{recipe}` | Delete recipe |
| POST | `/kitchen/{recipe}/scale` | Save scaled recipe as new |

### Ingredients

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/kitchen/{recipe}/ingredients` | Add ingredient |
| PUT | `/kitchen/ingredients/{ingredient}` | Update ingredient |
| DELETE | `/kitchen/ingredients/{ingredient}` | Remove ingredient |

### AJAX Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/kitchen/products/search` | Search POS products |
| GET | `/kitchen/{recipe}/costs` | Get recipe costs |
| POST | `/kitchen/{recipe}/recalculate` | Recalculate and save costs |

## Example Calculation

**Recipe: Apple Pie**
- Prep time: 30 min
- Cook time: 45 min
- Portions: 8
- Ingredient cost: €12.50
- Labour rate: €15/hr (default)
- Electricity rate: €0.25/kWh
- Cooking power: 2.0 kW
- Packaging: €0.50/portion (pie box)

**Calculation:**
```
Labour = (30 + 45) / 60 × €15 = 1.25 × €15 = €18.75
Electricity = 45 / 60 × 2.0 kW × €0.25 = 0.75 × 2.0 × €0.25 = €0.38
Packaging = €0.50 × 8 = €4.00
Total = €12.50 + €18.75 + €0.38 + €4.00 = €35.63
Per portion = €35.63 / 8 = €4.45
```

If linked to a product selling at €6.50:
```
Profit = €6.50 - €4.45 = €2.05
Margin = (€2.05 / €6.50) × 100 = 31.5% (Good)
```

### Scaling Example

Using the batch scaling calculator with 2x multiplier, 1.5x labour, 1.0x electricity:

```
Scaled Ingredients = €12.50 × 2 = €25.00
Scaled Labour = €18.75 × 1.5 = €28.13
Scaled Electricity = €0.38 × 1.0 = €0.38
Scaled Packaging = €0.50 × 16 = €8.00
Scaled Total = €25.00 + €28.13 + €0.38 + €8.00 = €61.51
Scaled Portions = 8 × 2 = 16
Scaled Per Portion = €61.51 / 16 = €3.84

Savings = €4.45 - €3.84 = €0.61/portion (13.7% savings)
```

## Files

### Models
- `app/Models/KitchenRecipe.php` - Recipe model with rate helpers
- `app/Models/KitchenRecipeIngredient.php` - Ingredient model
- `app/Models/KitchenIngredientProfile.php` - Profile model
- `app/Models/KitchenRecipeCostHistory.php` - Cost history model

### Services
- `app/Services/KitchenCostingService.php` - Cost calculation service

### Controllers
- `app/Http/Controllers/KitchenController.php` - Recipe CRUD
- `app/Http/Controllers/KitchenProfileController.php` - Profile management

### Views
- `resources/views/kitchen/index.blade.php` - Recipe listing
- `resources/views/kitchen/create.blade.php` - Create form
- `resources/views/kitchen/edit.blade.php` - Edit form with cost breakdown
- `resources/views/kitchen/show.blade.php` - Recipe details

### Configuration
- `config/kitchen.php` - Default rates configuration

## Related Documentation

- [Coffee KDS System](./kds-coffee-system.md) - Kitchen Display System for orders
- [Product Management](./product-management.md) - POS product integration
- [Supplier Integration](./supplier-integration.md) - Delivery markup for suppliers
