{{-- Drill-down modal, filled by resources/js/app.js when a figure is clicked.
     One per page; the front-end looks it up by #detailModal. --}}
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true"
     data-url="{{ route('admin.dashboard.detail') }}">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="h6 mb-0" id="detailModalJudul">Detail</h2>
                    <span class="card-hifi__meta" id="detailModalMeta"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="detailModalBody"></div>
        </div>
    </div>
</div>
