@if (session('success'))
    <div class="alert alert-success" role="status"><x-icon name="circle-check" /> <span>{{ session('success') }}</span></div>
@endif
@if (session('warning'))
    <div class="alert alert-warning" role="status"><x-icon name="circle-alert" /> <span>{{ session('warning') }}</span></div>
@endif
@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <x-icon name="circle-alert" />
        <div>
            <strong>Le formulaire contient des erreurs.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
