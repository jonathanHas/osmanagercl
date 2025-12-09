# Kitchen Recipe Costing System

The Kitchen Recipe Costing System provides comprehensive recipe management with ingredient costing, overhead calculations (labour and electricity), and profit margin analysis.

## Overview

This system enables you to:
- Create and manage recipes with ingredients linked to POS products
- Define ingredient profiles for accurate unit conversions and costing
- Calculate total recipe costs including ingredients, labour, and electricity
- Analyze profit margins when recipes are linked to POS products
- Track cost history over time for trend analysis

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

Use overrides for recipes with special requirements (e.g., commercial equipment, specialized labour).

### Cost Breakdown

The system displays a complete cost breakdown:
- **Ingredients**: Sum of all ingredient line costs
- **Labour**: Calculated from prep + cook time
- **Electricity**: Calculated from cook time
- **Total Cost**: Ingredients + Labour + Electricity
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

**Calculation:**
```
Labour = (30 + 45) / 60 × €15 = 1.25 × €15 = €18.75
Electricity = 45 / 60 × 2.0 kW × €0.25 = 0.75 × 2.0 × €0.25 = €0.375
Overhead = €18.75 + €0.38 = €19.13
Total = €12.50 + €19.13 = €31.63
Per portion = €31.63 / 8 = €3.95
```

If linked to a product selling at €6.50:
```
Profit = €6.50 - €3.95 = €2.55
Margin = (€2.55 / €6.50) × 100 = 39.2% (Good)
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
