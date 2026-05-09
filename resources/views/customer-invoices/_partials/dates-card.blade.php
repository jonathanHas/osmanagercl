<div class="card">
    <div class="card-head"><h3>Schedule &amp; notes</h3></div>
    <div class="grid-2">
        <div class="field">
            <label>Issue date <span class="req">*</span></label>
            <div class="date-input">
                <input type="date" required x-model="invoice.issue_date">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            </div>
        </div>
        <div class="field">
            <label>Due date</label>
            <div class="date-input">
                <input type="date" x-model="invoice.due_date">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            </div>
        </div>
    </div>
    <div class="field full">
        <label>Notes <span class="muted">(visible on invoice)</span></label>
        <textarea rows="2" placeholder="Thanks for your order — bank details on the second page." x-model="invoice.notes"></textarea>
    </div>
</div>
