<footer class="admin-footer">
    <div class="container-fluid px-3 px-lg-4">

        <span>
            Copyright {{ date('Y') }}
            {{ $generalSetting->company_name ?? 'My Company' }}.
            <br>

            {{ $generalSetting->footer_text ?? 'All rights reserved.' }}
        </span>

        <span>
            {{ $generalSetting->company_tagline ?? 'Professional Management System' }}
        </span>

    </div>
</footer>
