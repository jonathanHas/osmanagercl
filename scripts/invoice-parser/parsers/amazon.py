import re
import sys

def calculate_vat_breakdown(total_paid_eur, vat_amount_eur, vat_rate=0.23):
    """
    Helper function to calculate proper VAT breakdown for Amazon invoices
    
    Args:
        total_paid_eur (float): Actual amount paid in EUR
        vat_amount_eur (float): VAT amount in EUR 
        vat_rate (float): VAT rate (default 0.23 for 23%)
    
    Returns:
        dict: VAT breakdown with net amounts for each rate
    """
    # Calculate net amount at the VAT rate
    net_at_vat_rate = vat_amount_eur / vat_rate
    
    # Calculate any remainder (exchange rate difference, shipping, etc.)
    total_with_vat = net_at_vat_rate + vat_amount_eur
    remainder = total_paid_eur - total_with_vat
    
    # Map to Irish VAT rates
    vat_breakdown = {
        "vat_0": max(0, remainder),  # Exchange rate difference, shipping, etc.
        "vat_9": 0.0,               # UK reduced rate (not common for Amazon)
        "vat_13_5": 0.0,            # Not used by Amazon
        "vat_23": net_at_vat_rate   # Standard rate items
    }
    
    return {
        "breakdown": vat_breakdown,
        "total_calculated": sum(vat_breakdown.values()) + vat_amount_eur,
        "vat_amount": vat_amount_eur,
        "exchange_difference": remainder
    }

def parse_invoice(text, filename):
    print(f"[DEBUG] Parsing Amazon EU invoice: {filename}", file=sys.stderr)

    try:
        # === Invoice Date ===
        # Pattern: "Invoice date / Delivery date 17.07.2025"
        date_match = re.search(r'Invoice date.*?(\d{2}\.\d{2}\.\d{4})', text)
        if date_match:
            raw_date = date_match.group(1)
            # Convert from DD.MM.YYYY to DD/MM/YYYY
            invoice_date = raw_date.replace('.', '/')
        else:
            invoice_date = "Not found"
        
        print(f"[DEBUG] Invoice Date: {invoice_date}", file=sys.stderr)

        # === Invoice Number ===
        # Pattern: "Invoice # DS-AEU-INV-IE-2025-12878231" (but could vary)
        invoice_num_match = re.search(r'Invoice #\s*([A-Z0-9\-]+)', text)
        invoice_number = invoice_num_match.group(1) if invoice_num_match else "Not found"
        print(f"[DEBUG] Invoice Number: {invoice_number}", file=sys.stderr)

        # === Credit Note Detection ===
        is_credit_note = "Credit Note" in text or "Refund" in text
        print(f"[DEBUG] Credit Note: {is_credit_note}", file=sys.stderr)

        # === VAT Breakdown ===
        # Amazon invoices may show EUR amounts and VAT - we prioritize these for accuracy
        vat_totals = {
            "0": 0.0,
            "5": 0.0,    # UK reduced rate (mapped to 9% Irish)
            "20": 0.0,   # UK standard rate  
            "23": 0.0    # Irish standard rate (or mapped from UK 20%)
        }
        
        currency_used = "GBP"  # Default
        has_eur_vat = False
        eur_vat_amount = 0.0
        eur_total = 0.0
        
        # === Look for GBP VAT amounts ===
        gbp_vat_amount = 0
        gbp_total = None  # Will store the detected GBP total
        vat_rate = "23%"  # Default for Irish VAT
        
        # Look for VAT amount in GBP - use only VAT table format to avoid totals
        gbp_vat_patterns = [
            # Only use VAT table pattern: "23% £15.82 £3.64" (rate, net, vat)
            r'(\d+(?:\.\d+)?)%\s+£([\d,]+\.?\d*)\s+£([\d,]+\.?\d*)',  # VAT table: 23% £21.66 £4.98
            # Removed VAT (23%) £amount pattern as it matches invoice totals
        ]
        
        for pattern in gbp_vat_patterns:
            matches = re.findall(pattern, text, re.IGNORECASE)
            if matches:
                try:
                    match = matches[0]
                    if isinstance(match, tuple) and len(match) == 3:
                        # VAT table format: rate, net, vat
                        vat_rate = f"{match[0]}%"
                        gbp_vat_amount = float(match[2].replace(',', ''))  # Take VAT amount (3rd element)
                        print(f"[DEBUG] Found GBP VAT from table: £{gbp_vat_amount} at rate {vat_rate}", file=sys.stderr)
                        break
                    else:
                        print(f"[DEBUG] Unexpected VAT match format: {match}", file=sys.stderr)
                        continue
                except Exception as e:
                    print(f"[DEBUG] Could not parse GBP VAT: {e}", file=sys.stderr)
                    continue
        
        # === Look for EUR VAT Amount ===
        # Pattern: "Estimated VAT:" or just "VAT:" followed by EUR amount
        eur_vat_matches = re.findall(r'(?:Estimated\s+VAT|VAT).*?EUR\s*([\d,]+\.?\d*)', text, re.IGNORECASE)
        if not eur_vat_matches:
            # Alternative pattern: just "EUR X.XX" near VAT text
            eur_vat_matches = re.findall(r'VAT.*?EUR\s*([\d,]+\.?\d*)', text, re.IGNORECASE)
        
        if not eur_vat_matches:
            # Amazon-specific pattern: EUR amount appears after GBP VAT amount on separate line
            # Look for pattern like "£1.23\n€1.46" where € amount follows GBP VAT
            eur_after_gbp_pattern = r'£([\d,]+\.?\d*)\s*\n\s*[€€]([\d,]+\.?\d*)'
            eur_after_gbp = re.search(eur_after_gbp_pattern, text, re.MULTILINE)
            if eur_after_gbp:
                # Validate that the first amount looks like VAT (reasonable proportion of total)
                gbp_vat_candidate = float(eur_after_gbp.group(1).replace(',', ''))
                eur_vat_candidate = float(eur_after_gbp.group(2).replace(',', ''))
                # If GBP amount is reasonable VAT amount (10-30% of typical invoice)
                if 0.50 <= gbp_vat_candidate <= 500.0:  # Reasonable VAT range
                    eur_vat_matches = [eur_after_gbp.group(2)]
                    # Override any previously detected GBP VAT amount with the correct one
                    gbp_vat_amount = gbp_vat_candidate
                    print(f"[DEBUG] Found EUR VAT after GBP VAT pattern: £{gbp_vat_candidate} -> €{eur_vat_candidate}", file=sys.stderr)
                    print(f"[DEBUG] Corrected GBP VAT amount to: £{gbp_vat_amount}", file=sys.stderr)
        
        if not eur_vat_matches:
            # Fallback: Look for standalone € amounts in VAT-related sections
            standalone_eur_pattern = r'[€€]\s*([\d,]+\.?\d*)'
            standalone_eur_amounts = re.findall(standalone_eur_pattern, text)
            if standalone_eur_amounts:
                # Take the last reasonable EUR amount (likely to be VAT)
                for amount_str in reversed(standalone_eur_amounts):
                    amount = float(amount_str.replace(',', ''))
                    if 0.10 <= amount <= 1000.0:  # Reasonable EUR VAT range
                        eur_vat_matches = [amount_str]
                        print(f"[DEBUG] Found standalone EUR amount: €{amount}", file=sys.stderr)
                        break
        
        if eur_vat_matches:
            try:
                eur_vat_amount = float(eur_vat_matches[-1].replace(',', ''))
                has_eur_vat = True
                currency_used = "EUR"
                print(f"[DEBUG] Found EUR VAT amount: €{eur_vat_amount}", file=sys.stderr)
            except:
                pass
        
        # === Look for EUR Total ===
        # Pattern: "Total:" or "Grand Total:" followed by EUR amount
        eur_total_matches = re.findall(r'(?:Grand\s+Total|Total).*?EUR\s*([\d,]+\.?\d*)', text, re.IGNORECASE)
        if eur_total_matches:
            try:
                eur_total = float(eur_total_matches[-1].replace(',', ''))
                currency_used = "EUR"
                print(f"[DEBUG] Found EUR total: €{eur_total}", file=sys.stderr)
            except:
                pass
        
        # === Process EUR amounts if found ===
        if has_eur_vat and eur_vat_amount > 0:
            # Also look for GBP total when we have EUR VAT for display purposes
            if not gbp_total:
                total_patterns = [
                    r'(?:Total payable|Invoice total|Total)\s+£([\d,]+\.?\d*)',
                    r'Invoice total\s+£([\d,]+\.?\d*)',
                    r'Invoice total\s*\n\s*£([\d,]+\.?\d*)',
                    r'(?:Total payable|Total)\s*\n\s*£([\d,]+\.?\d*)',
                ]
                
                for pattern in total_patterns:
                    total_matches = re.findall(pattern, text, re.IGNORECASE)
                    if total_matches:
                        try:
                            gbp_total = float(total_matches[0].replace(',', ''))
                            print(f"[DEBUG] Found GBP total with EUR VAT: £{gbp_total}", file=sys.stderr)
                            break
                        except Exception as e:
                            print(f"[DEBUG] Could not parse GBP total: {e}", file=sys.stderr)
            # Calculate net amount from VAT (assuming 23% rate for EU purchases)
            net_amount_23 = eur_vat_amount / 0.23
            vat_totals["23"] = net_amount_23
            
            # If we have total from invoice, calculate any 0% VAT remainder
            if eur_total > 0:
                calculated_total_with_vat = net_amount_23 + eur_vat_amount
                remainder = eur_total - calculated_total_with_vat
                if abs(remainder) > 0.01:  # More than 1 cent difference
                    vat_totals["0"] = remainder
                    print(f"[DEBUG] EUR remainder (exchange/rounding): €{remainder:.2f}", file=sys.stderr)
            
            print(f"[DEBUG] EUR VAT calculation: Net at 23% = €{net_amount_23:.2f}, VAT = €{eur_vat_amount:.2f}", file=sys.stderr)
        
        # === Fallback to GBP VAT table if no EUR found ===
        elif not has_eur_vat:
            print(f"[DEBUG] No EUR VAT found, looking for GBP VAT table", file=sys.stderr)
            
            # Look for VAT summary table entries (lines that start with rate)
            # Pattern: "VAT_RATE% £NET_AMOUNT £VAT_AMOUNT" at start of line or after newline
            vat_lines = re.findall(r'(?:^|\n)(\d+(?:\.\d+)?)%\s+£([\d,]+\.?\d*)\s+£([\d,]+\.?\d*)', text, re.MULTILINE)
            print(f"[DEBUG] GBP VAT Summary Lines: {vat_lines}", file=sys.stderr)
            
            # Also look for GBP total even when we have VAT table
            # This ensures we capture the invoice total for display purposes
            total_patterns = [
                r'(?:Total payable|Invoice total|Total)\s+£([\d,]+\.?\d*)',
                r'Invoice total\s+£([\d,]+\.?\d*)',
                r'Invoice total\s*\n\s*£([\d,]+\.?\d*)',
                r'(?:Total payable|Total)\s*\n\s*£([\d,]+\.?\d*)',
            ]
            
            total_amount = None
            for i, pattern in enumerate(total_patterns):
                total_matches = re.findall(pattern, text, re.IGNORECASE)
                if total_matches:
                    try:
                        total_amount = float(total_matches[0].replace(',', ''))  # Take first specific match
                        gbp_total = total_amount
                        print(f"[DEBUG] Found GBP total alongside VAT table: £{total_amount}", file=sys.stderr)
                        break
                    except Exception as e:
                        print(f"[DEBUG] Could not parse GBP total: {e}", file=sys.stderr)

            for rate, net_amount, vat_amount in vat_lines:
                try:
                    # Clean and parse amounts
                    net_clean = float(net_amount.replace(',', ''))
                    
                    # Map to our standard rates
                    if rate == "0" or rate == "0.0":
                        vat_totals["0"] += net_clean
                    elif rate == "5" or rate == "5.0":
                        vat_totals["5"] += net_clean  # UK 5% mapped to Irish 9%
                    elif rate == "20" or rate == "20.0":
                        vat_totals["23"] += net_clean  # UK 20% mapped to Irish 23%
                    else:
                        # Unknown rate - add to closest match
                        rate_float = float(rate)
                        if rate_float < 10:
                            vat_totals["0"] += net_clean
                        elif rate_float < 15:
                            vat_totals["5"] += net_clean
                        else:
                            vat_totals["23"] += net_clean
                            
                    print(f"[DEBUG] Added VAT {rate}%: £{net_clean} to category", file=sys.stderr)
                    
                except Exception as e:
                    print(f"[DEBUG] Skipping malformed VAT line: {rate}%, {net_amount} — {e}", file=sys.stderr)
        
        # === Final fallback - total amount only ===
        if sum(vat_totals.values()) == 0:
            print(f"[DEBUG] No VAT breakdown found, looking for total amount", file=sys.stderr)
            
            # Look for GBP total first - try multiple patterns
            total_patterns = [
                # Direct patterns (amount on same line)
                r'(?:Total payable|Invoice total|Total)\s+£([\d,]+\.?\d*)',
                r'Invoice total\s+£([\d,]+\.?\d*)',
                # Multi-line patterns (amount on next line)
                r'Invoice total\s*\n\s*£([\d,]+\.?\d*)',
                r'(?:Total payable|Total)\s*\n\s*£([\d,]+\.?\d*)',
                # Find the largest £ amount (likely the total)
                r'£([\d,]+\.?\d*)',  # All £ amounts - will pick largest
            ]
            
            total_amount = None
            for i, pattern in enumerate(total_patterns):
                total_matches = re.findall(pattern, text, re.IGNORECASE)
                if total_matches:
                    try:
                        if i == len(total_patterns) - 1:  # Last pattern - pick largest
                            amounts = [float(m.replace(',', '')) for m in total_matches]
                            total_amount = max(amounts)  # Pick the largest amount
                            print(f"[DEBUG] Found GBP total: £{total_amount} (largest of {len(amounts)} amounts)", file=sys.stderr)
                        else:
                            total_amount = float(total_matches[-1].replace(',', ''))
                            print(f"[DEBUG] Found GBP total: £{total_amount} using pattern: {pattern}", file=sys.stderr)
                        break
                    except Exception as e:
                        print(f"[DEBUG] Could not parse GBP total: {e}", file=sys.stderr)
            
            if total_amount:
                # Store the GBP total for display purposes
                gbp_total = total_amount
                
                # If we found EUR VAT, calculate proper breakdown
                if has_eur_vat and eur_vat_amount > 0:
                    # Calculate net from EUR VAT (23% rate)
                    net_23_from_eur = eur_vat_amount / 0.23
                    vat_totals["23"] = net_23_from_eur
                    # Any remainder goes to 0% VAT
                    expected_total = net_23_from_eur * 1.23  # Net + VAT
                    if total_amount > expected_total:
                        vat_totals["0"] = total_amount - expected_total
                    print(f"[DEBUG] EUR VAT breakdown: €{eur_vat_amount} VAT -> £{net_23_from_eur:.2f} net at 23%", file=sys.stderr)
                else:
                    # No VAT breakdown found, assume 0% VAT
                    vat_totals["0"] = total_amount
                    print(f"[DEBUG] No VAT info, treating £{total_amount} as 0% VAT", file=sys.stderr)
            
            # Try EUR total if GBP failed
            elif eur_total > 0:
                vat_totals["0"] = eur_total
                currency_used = "EUR"
                print(f"[DEBUG] Using EUR total: €{eur_total}, assuming 0% VAT", file=sys.stderr)

        # === Tax Free Check ===
        # Amazon EU invoices might be tax-free for certain items
        total_vat_base = sum(vat_totals.values())
        tax_free = vat_totals["0"] > 0 and vat_totals["0"] == total_vat_base
        
        # Build the parsed data with proper currency information
        currency_note = ""
        if currency_used == "EUR":
            if has_eur_vat:
                currency_note = f"EUR amounts used - VAT: €{eur_vat_amount:.2f} at 23%"
            else:
                currency_note = "EUR total used - manual VAT review may be needed"
        else:
            currency_note = "GBP amounts converted - manual EUR adjustment needed"
        
        parsed_data = {
            'Filename': filename,
            'Supplier': 'Amazon',
            'Invoice Date': invoice_date,
            'Tax Free': tax_free,
            'Credit Note': is_credit_note,
            'VAT 0%': f"{vat_totals['0']:.2f}",
            'VAT 9%': f"{vat_totals['5']:.2f}",     # UK 5% mapped to Irish 9%
            'VAT 13.5%': '0.00',                    # Not commonly used by Amazon
            'VAT 23%': f"{vat_totals['23']:.2f}",   # Irish 23% or mapped from UK 20%
            'Invoice Number': invoice_number,
            'Currency': currency_used,
            'Currency Note': currency_note,
            'EUR_VAT_Found': has_eur_vat,
            'EUR_VAT_Amount': f"{eur_vat_amount:.2f}" if has_eur_vat else "0.00",
            'EUR_Total_Found': eur_total if eur_total > 0 else None,
            # New GBP fields for display
            'GBP_Total': gbp_total,
            'GBP_VAT_Amount': f"{gbp_vat_amount:.2f}" if gbp_vat_amount > 0 else "0.00",
            'VAT_Rate': vat_rate
        }

        print(f"[DEBUG] Parsed Data: {parsed_data}", file=sys.stderr)
        return parsed_data

    except Exception as e:
        print(f"[ERROR] Exception during parsing {filename}: {e}", file=sys.stderr)
        raise