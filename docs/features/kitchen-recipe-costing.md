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
labour_cost = (prep_time + cook_time × cook_supervision_factor) / 60 × labour_rate_per_hour
```

Prep time is fully attended work. Cook time is largely **unattended** — the dish is in the oven and nobody is standing over it — so only a fraction of it is charged as direct labour. With the default 10% factor, a 40 minute cook contributes 4 minutes of labour.

The full cook time is still charged as electricity, because the oven really is drawing power for all of it.

**Default Rate**: €15.00/hour (configurable via `KITCHEN_LABOUR_RATE` env variable)

**Default Supervision Factor**: 10% (configurable via `KITCHEN_COOK_SUPERVISION_FACTOR` env variable). Raise it globally if your recipes are typically hands-on stovetop work rather than oven bakes.

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
- **Labour Factor**: Scales **prep time** (e.g., a 2x batch might only need 1.5x the prep)
- **Electricity Factor**: Scales **cook time** (e.g., the same oven time for a larger batch)

The factors are applied to the recipe's **times**, because times are what a saved recipe stores and what the cost is computed from. Cook time drives electricity in full, plus a slice of labour via the supervision factor — so raising the electricity factor also nudges labour up slightly. The comparison table shows the resulting prep and cook times so the effect is visible before you save.

**Smart Defaults:**
When you change the recipe multiplier, suggested factors are automatically set:
| Multiplier | Labour Factor | Electricity Factor |
|------------|---------------|-------------------|
| 2x | 1.5x | 1.0x |
| 3-5x | 2.0x | 1.5x |
| 10x+ | 3.0x | 2.0x |

**Features:**
- Real-time comparison table showing original vs scaled times and costs
- Per-portion cost comparison with savings percentage
- **Save as New Recipe**: Create a scaled version as a separate recipe

**Scaling Calculation:**
```
scaled_prep_time = round(prep_time × labour_factor)
scaled_cook_time = round(cook_time × electricity_factor)

scaled_ingredients = original_ingredients × recipe_multiplier
scaled_labour = (scaled_prep_time + scaled_cook_time × cook_supervision_factor) / 60 × labour_rate
scaled_electricity = scaled_cook_time / 60 × cooking_power × electricity_rate
scaled_packaging = packaging_per_portion × (portions × recipe_multiplier)
scaled_total = scaled_ingredients + scaled_labour + scaled_electricity + scaled_packaging
scaled_portions = round(original_portions × recipe_multiplier)
scaled_cost_per_portion = scaled_total / scaled_portions
savings_percent = ((original_cost_per_portion - scaled_cost_per_portion) / original_cost_per_portion) × 100
```

> **The preview is what you get.** The calculator derives its figures from the scaled times using exactly the same formulas as the server, so *Save as New Recipe* produces a recipe whose recomputed costs match the preview. This requires `prep_time`, `cook_time` and the effective rates to be present in the costs payload — they are returned by `calculateRecipeCost()` for that purpose.

### Wholesale Pricing

`/kitchen/wholesale` prices **full batches** of each recipe for wholesale buyers and keeps a matching POS product in step. Reached from the **Wholesale** button on `/kitchen` or the sidebar.

**One wholesale unit is one full batch.** The cost basis is `calculateRecipeCost()['total_cost']` — the whole yield, not `cost_per_portion`. Pricing a batch against a per-portion cost would undercost it by the portion count, so `PRICEBUY` is written to the batch figure and a test pins it there.

**Prices are entered including VAT.** `PRODUCTS.PRICESELL` is stored ex-VAT, so every price crosses through the same conversion the products page uses:

```
ex_vat  = vat_rate > 0 ? inc_vat / (1 + vat_rate) : inc_vat
margin% = ex_vat > 0 ? (ex_vat - batch_cost) / ex_vat × 100 : null
target  = batch_cost / (1 - target%/100) × (1 + vat_rate)     // suggested inc-VAT price
```

The price is **never stored in Laravel** — it is always rebuilt from the product's own `PRICESELL` and `TAXCAT`, so a price edited directly in uniCenta still displays truthfully. What *is* stored is the target margin, because ingredient costs move with every delivery and without it the page could only show today's margin, not that a recipe had slipped below the margin that was agreed.

**Classification** is resolved most-explicit-first: an explicit request value → the existing wholesale product → the linked retail product. It never falls back to a 0% rate, which would store a 23% item's gross price as its net price. Recipes with no linked retail product are still pricable — the row renders category and VAT dropdowns and the save is rejected until both are chosen.

**Create vs update.** Setting a price on a recipe that already has a wholesale product **updates** it (`PRICESELL` and `PRICEBUY`) rather than creating a second one. `resolveExistingProduct()` checks the link column, then falls back to a name lookup so a product orphaned by a partial failure is adopted rather than duplicated; a link pointing at a product deleted in uniCenta clears itself and recreates.

**Name collisions.** `PRODUCTS.NAME` is unique. If `"<recipe> Wholesale"` is already taken by a *different* product, the name becomes `"<recipe> Wholesale [<code>]"`, unique by construction since codes are globally unique.

**The product is never renamed when the recipe is renamed** — the unique index and till button layouts both reference the name. The row displays the actual POS product name so the drift is visible rather than hidden. This is deliberate.

A price **below batch cost warns rather than blocks** (`price_below_cost`), as does a margin under target (`margin_below_target`). A loss-leader wholesale price is a legitimate business decision; blocking it would only push the work into uniCenta.

New products get the same treatment as a hand-created one: generated `CODE` (via `BarcodeGeneratorService`) with `REFERENCE = CODE`, a zero-unit `STOCKCURRENT` row, till visibility, `ProductMetadata` tagged `source: kitchen_wholesale`, and a `LabelLog` entry.

> **If you ever need more than one wholesale SKU per recipe** (half batch, tray, case), the 1:1 columns on `kitchen_recipes` stop being enough — that is the point to migrate to a `kitchen_wholesale_prices` table.

## Configuration

### Environment Variables

Add to your `.env` file:

```env
KITCHEN_LABOUR_RATE=15.00
KITCHEN_ELECTRICITY_RATE=0.25
KITCHEN_AVG_COOKING_POWER=2.0
KITCHEN_COOK_SUPERVISION_FACTOR=0.10
```

### Config File

Located at `config/kitchen.php`:

```php
return [
    'labour_rate' => env('KITCHEN_LABOUR_RATE', 15.00),
    'electricity_rate' => env('KITCHEN_ELECTRICITY_RATE', 0.25),
    'avg_cooking_power' => env('KITCHEN_AVG_COOKING_POWER', 2.0),
    'cook_supervision_factor' => env('KITCHEN_COOK_SUPERVISION_FACTOR', 0.10),
];
```

> **Note**: `cook_supervision_factor` is a global house rule, not a per-recipe override. Recipe costs are computed on every page load and nothing is stored, so changing it moves the numbers on every recipe immediately. Run `php artisan config:clear` after changing it in production.

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
| wholesale_pos_product_id | varchar | Linked wholesale POS product ID (one full batch). No FK — different connection |
| wholesale_target_margin | decimal(5,2) | Margin % the wholesale price was set to hit |
| wholesale_priced_at | timestamp | When the wholesale price was last set |
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
| ingredient_cost | decimal | Ingredient cost at time (nullable) |
| labour_cost | decimal | Labour cost at time (nullable) |
| labour_minutes | decimal | Chargeable labour minutes at time (nullable) |
| electricity_cost | decimal | Electricity cost at time (nullable) |
| packaging_cost | decimal | Packaging cost at time (nullable) |
| total_cost | decimal | Total recipe cost |
| cost_per_portion | decimal | Per-portion cost |
| sell_price | decimal | Sell price at time |
| margin_percentage | decimal | Margin at time |
| recorded_at | timestamp | When recorded |

The component columns are **nullable**: snapshots taken before they existed hold totals only and cannot be broken down after the fact. A null means "not captured", not zero — use `$history->hasBreakdown()` to tell the two apart, and chart the component series from `getCostTrends()` as gaps rather than zeroes.

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

### Wholesale

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/kitchen/wholesale` | Wholesale pricing page for all recipes |
| POST | `/kitchen/wholesale/{recipe}` | Create or update the wholesale product (JSON upsert) |

> Both **must** stay registered before the `/kitchen/{recipe}` wildcard in `routes/web.php`, or `GET /kitchen/wholesale` binds `{recipe} = 'wholesale'` and 404s. `KitchenWholesalePageTest` guards this.

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
Labour minutes = 30 prep + (45 cook × 10%) = 30 + 4.5 = 34.5 min
Labour = 34.5 / 60 × €15 = €8.63
Electricity = 45 / 60 × 2.0 kW × €0.25 = 0.75 × 2.0 × €0.25 = €0.38
Packaging = €0.50 × 8 = €4.00
Total = €12.50 + €8.63 + €0.38 + €4.00 = €25.51
Per portion = €25.51 / 8 = €3.19
```

Note that the 45 minutes in the oven costs €4.50 of labour less than the 45 minutes charged before the supervision factor was introduced, while the electricity charge is untouched.

If linked to a product selling at €6.50:
```
Profit = €6.50 - €3.19 = €3.31
Margin = (€3.31 / €6.50) × 100 = 50.9% (Excellent)
```

### Scaling Example

Using the batch scaling calculator with 2x multiplier, 1.5x labour, 1.0x electricity:

```
Scaled Prep = 30 × 1.5 = 45 min
Scaled Cook = 45 × 1.0 = 45 min

Scaled Ingredients = €12.50 × 2 = €25.00
Scaled Labour = (45 + 45 × 10%) / 60 × €15 = 49.5/60 × €15 = €12.38
Scaled Electricity = 45 / 60 × 2.0 kW × €0.25 = €0.38
Scaled Packaging = €0.50 × 16 = €8.00
Scaled Total = €25.00 + €12.38 + €0.38 + €8.00 = €45.76
Scaled Portions = 8 × 2 = 16
Scaled Per Portion = €45.76 / 16 = €2.86

Savings = €3.19 - €2.86 = €0.33/portion (10.3% savings)
```

Saving this as a new recipe stores **prep 45 / cook 45**, so reopening it recomputes to exactly the figures above.

## Files

### Models
- `app/Models/KitchenRecipe.php` - Recipe model with rate helpers
- `app/Models/KitchenRecipeIngredient.php` - Ingredient model
- `app/Models/KitchenIngredientProfile.php` - Profile model
- `app/Models/KitchenRecipeCostHistory.php` - Cost history model

### Services
- `app/Services/KitchenCostingService.php` - Cost calculation service
- `app/Services/KitchenWholesaleService.php` - Wholesale batch pricing and POS product upsert
- `app/Services/BarcodeGeneratorService.php` - Next-available product code (shared with the products page)

### Controllers
- `app/Http/Controllers/KitchenController.php` - Recipe CRUD
- `app/Http/Controllers/KitchenProfileController.php` - Profile management
- `app/Http/Controllers/KitchenWholesaleController.php` - Wholesale pricing page

### Requests
- `app/Http/Requests/StoreWholesalePriceRequest.php` - Wholesale price validation

### Views
- `resources/views/kitchen/index.blade.php` - Recipe listing
- `resources/views/kitchen/create.blade.php` - Create form
- `resources/views/kitchen/edit.blade.php` - Edit form with cost breakdown
- `resources/views/kitchen/show.blade.php` - Recipe details
- `resources/views/kitchen/wholesale.blade.php` - Wholesale pricing grid

### Tests
- `tests/Unit/KitchenWholesaleMarginTest.php` - Price/margin arithmetic (no DB)
- `tests/Feature/KitchenWholesalePricingTest.php` - Product create/update, inheritance, warnings
- `tests/Feature/KitchenWholesalePageTest.php` - Route ordering and auth
- `tests/Feature/BarcodeGeneratorServiceTest.php` - Barcode generation regression net

### Configuration
- `config/kitchen.php` - Default rates configuration

## Related Documentation

- [Kitchen Products Management](./kitchen-products.md) - Track products for kitchen and speed up profile creation
- [Coffee KDS System](./kds-coffee-system.md) - Kitchen Display System for orders
- [Product Management](./product-management.md) - POS product integration
- [Supplier Integration](./supplier-integration.md) - Delivery markup for suppliers
