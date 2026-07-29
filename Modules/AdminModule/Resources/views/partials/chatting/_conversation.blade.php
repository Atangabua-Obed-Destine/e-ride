{{-- Files Uploaded state --}}
<div class="card card-body card-chat justify-content-center px-0 pb-0 border 0 h-100 chat_box_state" id="">
    <div class="px-12">
        <div
            class="inbox_msg_header border px-3 py-2 rounded mb-4 d-flex justify-content-center align-items-center gap-3">
            <div class="media align-items-center gap-10 flex-grow-1">
                <div width="32" class="avatar avatar-sm rounded-circle position-relative">
                    <img src="{{ onErrorImage(
                        $driver?->profile_image,
                        dynamicStorage('storage/app/public/driver/profile') . '/' . $driver?->profile_image,
                        dynamicAsset('public/assets/admin-module/img/user.png'),
                        'driver/profile/',
                    ) }}"
                        id="" class="avatar-img rounded-circle aspect-1" alt="">
                    {{--                    <span class="avatar-status avatar-status-success small"></span> --}}
                </div>
                <div class="chat_ib media-body title-color">
                    <h6 class="mb-1 seller active-text fw-semibold">
                        {{ $driver?->full_name ?? ($driver?->first_name ? $driver?->first_name . ' ' . $driver?->last_name : 'N/A') }}

                    </h6>
                    <div class="fs-12 opacity-50 d-block" id=""> {{ $driver?->phone ?? 'N/A' }}
                    </div>
                </div>
            </div>
            <div class="dropdown">
                <button type="button" class="btn border-0 fs-20 p-0" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right primary" style="">
                    <li>
                        <a class="dropdown-item fs-16 py-3"
                            href="{{ route('admin.driver.show', ['id' => $driver?->id]) }}"
                            target="_blank">{{ translate('Driver Details') }}</a>
                        <a class="dropdown-item fs-16 py-3"
                            href="{{ route('admin.driver.show', ['id' => $driver?->id, 'tab' => 'trips']) }}"
                            target="_blank">{{ translate('Trip List') }}</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="card-body p-3 overflow-y-auto conversation flex-grow-1 msg_history d-flex flex-column-reverse"
        id="chatting-messages-section">


        @foreach ($conversations as $conversation)
            @if ($conversation->user->user_type == DRIVER)
                <div class="incoming_msg d-flex align-items-end gap-2">
                    <div class="">
                        <img class="avatar-img user-avatar-image border inbox-user-avatar-25 aspect-1"
                            id="profile_image" width="40" height="40"
                            src="{{ onErrorImage(
                                $driver?->profile_image,
                                dynamicStorage('storage/app/public/driver/profile') . '/' . $driver?->profile_image,
                                dynamicAsset('public/assets/admin-module/img/user.png'),
                                'driver/profile/',
                            ) }}"
                            alt="Image Description">
                    </div>
                    <div class="multiple-msg d-flex flex-column gap-2">
                        @if ($conversation?->message)
                            <div class="received_msg" data-bs-toggle="tooltip" data-bs-title="{{ formatCustomDateForTooltip($conversation?->created_at) }}">
                                <div class="received_withdraw_msg">
                                    <div class="message-text-section rounded mt-1">
                                        <p class="m-0 pb-1">
                                            {{ $conversation->message }}
                                        </p>
                                        <span class="small text-end w-100 d-block text-muted"></span>
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if ($conversation?->conversation_files && count($conversation?->conversation_files) > 0)
                            @php
                                $documentFiles = [];
                                $imageFiles = [];
                                foreach ($conversation?->conversation_files as $file) {
                                    if (in_array($file->file_type, ['png', 'jpg', 'jpeg'])) {
                                        $imageFiles[] = $file;
                                    } else {
                                        $documentFiles[] = $file;
                                    }
                                }
                            @endphp
                            @if (count($imageFiles) > 0 && count($imageFiles) == 1)
                                <div class="received_msg mb-0">
                                    <div class="sent_msg p-2" data-bs-toggle="tooltip"
                                        data-bs-title="{{ formatCustomDateForTooltip($conversation?->created_at) }}">
                                        <div class="d-flex flex-wrap mb-2">
                                            <div class="row g-1 align-items-center pt-1 w-140">
                                                @foreach ($imageFiles as $key => $file)
                                                    <div class="col-6 position-relative img_row{{ $key }}">
                                                        <a data-bs-toggle="modal"
                                                            data-bs-target="#imgViewModal{{ $conversation->id }}"
                                                            href="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}"
                                                            class="aspect-1 overflow-hidden d-block border rounded position-relative">
                                                            <img class="img-fit" alt=""
                                                                src="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}">
                                                        </a>
                                                    </div>
                                                    <a download
                                                        href="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}"
                                                        class="btn btn-light btn--download left-50">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- img view modal --}}
                                <div class="modal fade imgViewModal" id="imgViewModal{{ $conversation->id }}"
                                    tabindex="-1" aria-labelledby="imgViewModal{{ $conversation->id }}Label"
                                    role="dialog" aria-modal="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header d-flex justify-content-end border-0">
                                                <button type="button" class="btn-close p-1" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body pt-0">
                                                <div class="imgView-slider owl-theme owl-carousel" dir="ltr">
                                                    @foreach ($imageFiles as $file)
                                                        <div class="imgView-item">
                                                            <div
                                                                class="d-flex justify-content-between align-items-center gap-3 mb-10">
                                                                <h6 class="fs-12 img-title">{{ $file->file_name }}</h6>
                                                                <a type="button"
                                                                    class="btn btn-light rounded-05 d-flex gap-2 px-2"
                                                                    href="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}"
                                                                    download>
                                                                    {{ translate('Download') }}
                                                                    <button type="btn"
                                                                        class="btn btn-light p-1 text-primary btn--download d-flex justify-content-center align-items-center">
                                                                        <i class="bi bi-download"></i>
                                                                    </button>
                                                                </a>
                                                            </div>
                                                            <div class="image-wrapper">
                                                                <img class="" alt=""
                                                                    src="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}">
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                                <div class="imgView-slider_buttons d-flex justify-content-center"
                                                    dir="ltr">
                                                    <button type="button" class="btn owl-btn imgView-owl-prev">
                                                        <i class="tio-chevron-left"></i>
                                                    </button>
                                                    <button type="button" class="btn owl-btn imgView-owl-next">
                                                        <i class="tio-chevron-right"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- img view modal ends --}}
                            @elseif(count($imageFiles) > 0 && count($imageFiles) > 1)
                                <div class="received_msg" data-bs-toggle="tooltip"
                                     data-bs-title="{{ formatCustomDateForTooltip($conversation?->created_at) }}">
                                    <div
                                        class="zip-wrapper d-flex gap-2 justify-content-end align-items-center flex-row-reverse flex-wrap mb-2 position-relative">
                                        <a download href="javascript:"
                                            class="btn btn-light btn--download zip-download right-150">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <div
                                            class="row g-1 flex-wrap pt-1 justify-content-start w-140 zip-images">
                                            @foreach ($imageFiles as $key => $file)

                                                <div
                                                    class="col-6 position-relative img_row{{ $key }} {{ $key > 3 ? 'd-none' : '' }}">
                                                    <a data-bs-toggle="modal"
                                                        data-bs-target="#imgViewModal{{ $conversation->id }}"
                                                        href="javascript:void(0)"
                                                        class="aspect-1 overflow-hidden d-block border rounded position-relative"
                                                        data-index="{{ $key }}">
                                                            <img class="img-fit" alt=""
                                                            src="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}">
                                                        @if ($key == 3)
                                                            <div class="extra-images {{ (count($imageFiles) - 4) == 0 ? 'd-none' : '' }}">
                                                                <span
                                                                    class="extra-image-count">+{{ count($imageFiles) - 4 }}</span>
                                                            </div>
                                                        @endif
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                {{-- img view modal --}}
                                <div class="modal fade imgViewModal" id="imgViewModal{{ $conversation->id }}"
                                    tabindex="-1" aria-labelledby="imgViewModal{{ $conversation->id }}Label"
                                    role="dialog" aria-modal="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header d-flex justify-content-end border-0">
                                                <button type="button" class="btn-close p-1" data-bs-dismiss="modal"
                                                    aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body pt-0">
                                                <div class="imgView-slider owl-theme owl-carousel" dir="ltr">
                                                    @foreach ($imageFiles as $file)
                                                        <div class="imgView-item">
                                                            <div
                                                                class="d-flex justify-content-between align-items-center gap-3 mb-10">
                                                                <h6 class="fs-12 img-title">{{ $file->file_name }}
                                                                </h6>
                                                                <a type="button"
                                                                    class="btn btn-light rounded-05 d-flex gap-2 px-2"
                                                                    href="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}"
                                                                    download>
                                                                    {{ translate('Download') }}
                                                                    <button type="btn"
                                                                        class="btn btn-light p-1 text-primary btn--download d-flex justify-content-center align-items-center">
                                                                        <i class="bi bi-download"></i>
                                                                    </button>
                                                                </a>
                                                            </div>
                                                            <div class="image-wrapper">
                                                                <img class="" alt=""
                                                                    src="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}">
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                                <div class="imgView-slider_buttons d-flex justify-content-center"
                                                    dir="ltr">
                                                    <button type="button" class="btn owl-btn imgView-owl-prev">
                                                        <i class="tio-chevron-left"></i>
                                                    </button>
                                                    <button type="button" class="btn owl-btn imgView-owl-next">
                                                        <i class="tio-chevron-right"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- img view modal ends --}}
                            @endif
                            @if (count($documentFiles) > 0)
                                <div class="received_msg" data-bs-toggle="tooltip"
                                     data-bs-title="{{ formatCustomDateForTooltip($conversation?->created_at) }}">
                                    <div class="d-flex flex-wrap mb-2">
                                        <div class="row g-1 flex-wrap pt-1 justify-content-end w-140">
                                            @foreach ($documentFiles as $file)
                                                <div class="d-flex">
                                                    <a class="d-flex gap-3 align-items-center"
                                                        href="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}"
                                                        target="_blank">
                                                        <div class="uploaded-file-item">
                                                            <img src="{{ dynamicAsset('public/assets/admin-module/img/word-icon.png') }}"
                                                                class="file-icon" alt="">
                                                            <div class="upload-file-item-content">
                                                                <div class="title-color">
                                                                    {{ $file?->file_name }}
                                                                </div>
                                                                <small>
                                                                    @if (file_exists(storage_path('app/public/conversation/' . $file?->file_name)))
                                                                        {{ number_format(filesize(storage_path('app/public/conversation/' . $file?->file_name)) / 1024, 2) }} KB
                                                                    @else
                                                                        File size undefined
                                                                    @endif
                                                                </small>
                                                            </div>
                                                            <a href="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}"
                                                                class="btn btn-light btn--download" download>
                                                                <i class="bi bi-download"></i>
                                                            </a>
                                                        </div>
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @else
                @if ($conversation?->message)
                    <div class="outgoing_msg mb-0">

                        <div class="sent_msg p-2" data-bs-toggle="tooltip"
                             data-bs-title="{{ formatCustomDateForTooltip($conversation?->created_at) }}">
                            <div class="message-text-section rounded mt-1">
                                <p class="m-0 pb-1">
                                    {{ $conversation?->message }}
                                </p>
                                <span class="small text-start w-100 d-block text-muted"></span>
                            </div>
                        </div>
                    </div>
                @endif
                @if ($conversation?->conversation_files && count($conversation?->conversation_files) > 0)
                    @php
                        $documentFiles = [];
                        $imageFiles = [];
                        foreach ($conversation?->conversation_files as $file) {
                            if (in_array($file->file_type, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
                                $imageFiles[] = $file;
                            } else {
                                $documentFiles[] = $file;
                            }
                        }
                    @endphp
                    @if (count($imageFiles) > 0 && count($imageFiles) == 1)
                        <div class="outgoing_msg mb-0">
                            <div class="sent_msg p-2" data-bs-toggle="tooltip"
                                 data-bs-title="{{ formatCustomDateForTooltip($conversation?->created_at) }}">
                                <div class="d-flex justify-content-end flex-wrap mb-2">
                                    <div class="row g-1 flex-wrap pt-1 justify-content-end w-140">
                                        @foreach ($imageFiles as $key => $file)
                                            <div class="col-6 position-relative img_row{{ $key }}">
                                                <a download
                                                    href="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}"
                                                    class="btn btn-light btn--download left-50">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <a data-bs-toggle="modal"
                                                    data-bs-target="#imgViewModal{{ $conversation->id }}"
                                                    href="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}"
                                                    class="aspect-1 overflow-hidden d-block border rounded position-relative">
                                                    <img class="img-fit" alt=""
                                                        src="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}">
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- img view modal --}}
                        <div class="modal fade imgViewModal" id="imgViewModal{{ $conversation->id }}" tabindex="-1"
                            aria-labelledby="imgViewModal{{ $conversation->id }}Label" role="dialog"
                            aria-modal="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header d-flex justify-content-end border-0">
                                        <button type="button" class="btn-close p-1" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body pt-0">
                                        <div class="imgView-slider owl-theme owl-carousel" dir="ltr">
                                            @foreach ($imageFiles as $file)
                                                <div class="imgView-item">
                                                    <div
                                                        class="d-flex justify-content-between align-items-center gap-3 mb-10">
                                                        <h6 class="fs-12 img-title">{{ $file->file_name }}</h6>
                                                        <a type="button"
                                                            class="btn btn-light rounded-05 d-flex gap-2 px-2"
                                                            href="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}"
                                                            download>
                                                            {{ translate('Download') }}
                                                            <button type="btn"
                                                                class="btn btn-light p-1 text-primary btn--download d-flex justify-content-center align-items-center">
                                                                <i class="bi bi-download"></i>
                                                            </button>
                                                        </a>
                                                    </div>
                                                    <div class="image-wrapper">
                                                        <img class="" alt=""
                                                            src="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}">
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="imgView-slider_buttons d-flex justify-content-center"
                                            dir="ltr">
                                            <button type="button" class="btn owl-btn imgView-owl-prev">
                                                <i class="tio-chevron-left"></i>
                                            </button>
                                            <button type="button" class="btn owl-btn imgView-owl-next">
                                                <i class="tio-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- img view modal ends --}}
                    @elseif(count($imageFiles) > 0 && count($imageFiles) > 1)
                        <div class="outgoing_msg mb-0">
                            <div class="sent_msg p-2" data-bs-toggle="tooltip"
                                 data-bs-title="{{ formatCustomDateForTooltip($conversation?->created_at) }}">
                                <div
                                    class="zip-wrapper d-flex gap-2 justify-content-end align-items-center flex-wrap mb-2 position-relative">
                                    <a download href="javascript:"
                                        class="btn btn-light btn--download zip-download right-150">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <div class="row g-1 flex-wrap pt-1 justify-content-end w-140 zip-images">
                                        @foreach ($imageFiles as $key => $file)

                                            <div
                                                class="col-6 position-relative img_row{{ $key }} {{ $key > 3 ? 'd-none' : '' }}">
                                                <a data-bs-toggle="modal"
                                                    data-bs-target="#imgViewModal{{ $conversation->id }}"
                                                    href="javascript:void(0)"
                                                    class="aspect-1 overflow-hidden d-block border rounded position-relative"
                                                    data-index="{{ $key }}">
                                                    <img class="img-fit" alt=""
                                                        src="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}">
                                                    @if ($key == 3)
                                                        <div class="extra-images {{ (count($imageFiles) - 4) == 0 ? 'd-none' : '' }}">
                                                            <span
                                                                class="extra-image-count">+{{ count($imageFiles) - 4 }}</span>
                                                        </div>
                                                    @endif
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- img view modal --}}
                        <div class="modal fade imgViewModal" id="imgViewModal{{ $conversation->id }}" tabindex="-1"
                            aria-labelledby="imgViewModal{{ $conversation->id }}Label" role="dialog"
                            aria-modal="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header d-flex justify-content-end border-0">
                                        <button type="button" class="btn-close p-1" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body pt-0">
                                        <div class="imgView-slider owl-theme owl-carousel" dir="ltr">
                                            @foreach ($imageFiles as $file)
                                                <div class="imgView-item">
                                                    <div
                                                        class="d-flex justify-content-between align-items-center gap-3 mb-10">
                                                        <h6 class="fs-12 img-title">{{ $file->file_name }}</h6>
                                                        <a type="button"
                                                            class="btn btn-light rounded-05 d-flex gap-2 px-2"
                                                            href="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}"
                                                            download>
                                                            {{ translate('Download') }}
                                                            <button type="btn"
                                                                class="btn btn-light p-1 text-primary btn--download d-flex justify-content-center align-items-center">
                                                                <i class="bi bi-download"></i>
                                                            </button>
                                                        </a>
                                                    </div>
                                                    <div class="image-wrapper">
                                                        <img class="" alt=""
                                                            src="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}">
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="imgView-slider_buttons d-flex justify-content-center"
                                            dir="ltr">
                                            <button type="button" class="btn owl-btn imgView-owl-prev">
                                                <i class="tio-chevron-left"></i>
                                            </button>
                                            <button type="button" class="btn owl-btn imgView-owl-next">
                                                <i class="tio-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- img view modal ends --}}
                    @endif
                    @if (count($documentFiles) > 0)
                        <div class="outgoing_msg mb-0">
                            <div class="sent_msg p-2" data-bs-toggle="tooltip"
                                 data-bs-title="{{ formatCustomDateForTooltip($conversation?->created_at) }}">
                                <div class="d-flex justify-content-end flex-wrap mb-2">
                                    <div class="row g-1 flex-wrap pt-1 justify-content-end w-140">
                                        @foreach ($documentFiles as $key => $file)
                                            <div class="d-flex gap-2 justify-content-end">
                                                <a class="d-flex gap-3 align-items-center"
                                                    href="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}"
                                                    target="_blank">
                                                    <div class="uploaded-file-item">
                                                        <img src="{{ dynamicAsset('public/assets/admin-module/img/word-icon.png') }}"
                                                            class="file-icon" alt="">
                                                        <div class="upload-file-item-content">
                                                            <div class="title-color">
                                                                {{ $file->file_name }}
                                                            </div>
                                                            <small>
                                                                @if (file_exists(storage_path('app/public/conversation/' . $file?->file_name)))
                                                                    {{ number_format(filesize(storage_path('app/public/conversation/' . $file?->file_name)) / 1024, 2) }} KB
                                                                @else
                                                                    File size undefined
                                                                @endif
                                                            </small>
                                                        </div>
                                                        <a href="{{ dynamicStorage('storage/app/public/conversation') . '/' . $file?->file_name }}"
                                                            class="btn btn-light btn--download" download>
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                    </div>
                                                </a>
                                            </div>
                                        @endforeach

                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            @endif
        @endforeach
    </div>


    <div class="type_msg">
        <div class="input_msg_write">
            <form class="mt-4 chatting-messages-ajax-form" enctype="multipart/form-data" method="POST">
                @csrf
                <div class="position-relative d-flex">
                    <div class="d-flex align-items-center m-0 position-absolute top-3 px-3 gap-2">
                        <label class="py-0 cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22"
                                viewBox="0 0 22 22" fill="none">
                                <path
                                    d="M18.1029 1.83203H3.89453C2.75786 1.83203 1.83203 2.75786 1.83203 3.89453V18.1029C1.83203 19.2395 2.75786 20.1654 3.89453 20.1654H18.1029C19.2395 20.1654 20.1654 19.2395 20.1654 18.1029V3.89453C20.1654 2.75786 19.2395 1.83203 18.1029 1.83203ZM3.89453 3.20703H18.1029C18.4814 3.20703 18.7904 3.51595 18.7904 3.89453V12.7642L15.2539 9.2277C15.1255 9.09936 14.9514 9.02603 14.768 9.02603H14.7653C14.5819 9.02603 14.405 9.09936 14.2776 9.23136L10.3204 13.25L8.65845 11.5945C8.53011 11.4662 8.35595 11.3929 8.17261 11.3929C7.9957 11.3654 7.81053 11.4662 7.6822 11.6009L3.20703 16.1705V3.89453C3.20703 3.51595 3.51595 3.20703 3.89453 3.20703ZM3.21253 18.1304L8.17903 13.0575L13.9375 18.7904H3.89453C3.52603 18.7904 3.22811 18.4952 3.21253 18.1304ZM18.1029 18.7904H15.8845L11.2948 14.2189L14.7708 10.6898L18.7904 14.7084V18.1029C18.7904 18.4814 18.4814 18.7904 18.1029 18.7904Z"
                                    fill="#1455AC" />
                                <path
                                    d="M8.12834 9.03012C8.909 9.03012 9.54184 8.39728 9.54184 7.61662C9.54184 6.83597 8.909 6.20312 8.12834 6.20312C7.34769 6.20312 6.71484 6.83597 6.71484 7.61662C6.71484 8.39728 7.34769 9.03012 8.12834 9.03012Z"
                                    fill="#1455AC" />
                            </svg>
                            <input type="file" id="select-image" name="images[]"
                                class="h-100 position-absolute w-100 " hidden multiple accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}">
                        </label>
                        <label class="py-0 cursor-pointer">
                            <svg width="20" height="18" viewBox="0 0 20 18" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M5.61597 17.2917C4.66813 17.2919 3.7415 17.011 2.95335 16.4845C2.16519 15.958 1.55092 15.2096 1.18827 14.3338C0.825613 13.4581 0.730874 12.4945 0.916037 11.5649C1.1012 10.6353 1.55794 9.78158 2.22847 9.11165L9.2993 2.03999C9.41655 1.92274 9.57557 1.85687 9.74139 1.85687C9.9072 1.85687 10.0662 1.92274 10.1835 2.03999C10.3007 2.15724 10.3666 2.31626 10.3666 2.48207C10.3666 2.64788 10.3007 2.80691 10.1835 2.92415L3.11181 9.99499C2.76945 10.3208 2.49576 10.7118 2.30686 11.145C2.11796 11.5782 2.01768 12.0449 2.01193 12.5175C2.00617 12.99 2.09506 13.459 2.27334 13.8967C2.45163 14.3344 2.71572 14.7319 3.05004 15.066C3.38436 15.4 3.78216 15.6638 4.21999 15.8417C4.65783 16.0196 5.12685 16.1081 5.59941 16.102C6.07198 16.0958 6.53854 15.9951 6.9716 15.8059C7.40465 15.6166 7.79545 15.3426 8.12097 15L17.2543 5.86665C17.6728 5.43446 17.9047 4.85506 17.8999 4.25344C17.895 3.65183 17.6539 3.07623 17.2285 2.65081C16.8031 2.22539 16.2275 1.98425 15.6258 1.97942C15.0242 1.97459 14.4448 2.20645 14.0126 2.62499L6.64764 9.99499C6.45226 10.1904 6.3425 10.4554 6.3425 10.7317C6.3425 11.008 6.45226 11.2729 6.64764 11.4683C6.84301 11.6637 7.108 11.7735 7.3843 11.7735C7.66061 11.7735 7.9256 11.6637 8.12097 11.4683L12.8335 6.75499C12.8911 6.69527 12.96 6.64762 13.0363 6.61483C13.1125 6.58204 13.1945 6.56476 13.2775 6.564C13.3605 6.56324 13.4428 6.57901 13.5196 6.6104C13.5964 6.64179 13.6663 6.68817 13.725 6.74682C13.7837 6.80548 13.8301 6.87524 13.8616 6.95203C13.893 7.02883 13.9089 7.11112 13.9082 7.19411C13.9075 7.27709 13.8903 7.35911 13.8576 7.43538C13.8249 7.51165 13.7773 7.58064 13.7176 7.63832L9.0043 12.3525C8.57454 12.7824 7.99162 13.0239 7.38377 13.024C6.77591 13.0241 6.19293 12.7827 5.76305 12.3529C5.33318 11.9231 5.09164 11.3402 5.09156 10.7324C5.09148 10.1245 5.33288 9.54153 5.76264 9.11165L13.1293 1.74999C13.7935 1.08573 14.6943 0.712511 15.6336 0.712433C16.5729 0.712355 17.4738 1.08542 18.1381 1.74957C18.8023 2.41372 19.1755 3.31454 19.1756 4.25386C19.1757 5.19318 18.8026 6.09406 18.1385 6.75832L9.00514 15.8883C8.56103 16.3347 8.03283 16.6885 7.45109 16.9294C6.86934 17.1703 6.24561 17.2934 5.61597 17.2917Z"
                                    fill="#46A046" />
                            </svg>
                            <input type="file" id="select-file" class="h-100 position-absolute w-100 " hidden
                                multiple accept="{{ FILE_ACCEPTED_EXTENSIONS }}">
                        </label>
                        <label class="py-0 cursor-pointer" id="trigger">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <g clip-path="url(#clip0_7224_10484)">
                                    <path
                                        d="M10 20C8.02219 20 6.08879 19.4135 4.4443 18.3147C2.79981 17.2159 1.51809 15.6541 0.761209 13.8268C0.00433286 11.9996 -0.193701 9.98891 0.192152 8.0491C0.578004 6.10929 1.53041 4.32746 2.92894 2.92894C4.32746 1.53041 6.10929 0.578004 8.0491 0.192152C9.98891 -0.193701 11.9996 0.00433286 13.8268 0.761209C15.6541 1.51809 17.2159 2.79981 18.3147 4.4443C19.4135 6.08879 20 8.02219 20 10C19.9971 12.6513 18.9426 15.1932 17.0679 17.0679C15.1932 18.9426 12.6513 19.9971 10 20ZM10 1.66667C8.35183 1.66667 6.74066 2.15541 5.37025 3.07109C3.99984 3.98677 2.93174 5.28826 2.30101 6.81098C1.67028 8.33369 1.50525 10.0092 1.82679 11.6258C2.14834 13.2423 2.94201 14.7271 4.10745 15.8926C5.27289 17.058 6.75774 17.8517 8.37425 18.1732C9.99076 18.4948 11.6663 18.3297 13.189 17.699C14.7118 17.0683 16.0132 16.0002 16.9289 14.6298C17.8446 13.2593 18.3333 11.6482 18.3333 10C18.3309 7.79061 17.4522 5.67241 15.8899 4.11013C14.3276 2.54785 12.2094 1.6691 10 1.66667ZM14.7217 13.1217C14.8868 12.9747 14.9867 12.7682 14.9995 12.5475C15.0123 12.3268 14.937 12.1101 14.79 11.945C14.643 11.7799 14.4365 11.68 14.2158 11.6671C13.9952 11.6543 13.7784 11.7297 13.6133 11.8767C12.5946 12.7344 11.3288 13.2447 10 13.3333C8.67202 13.2448 7.40686 12.7351 6.38834 11.8783C6.22346 11.7311 6.00687 11.6555 5.7862 11.668C5.56553 11.6805 5.35887 11.7801 5.21167 11.945C5.06448 12.1099 4.98881 12.3265 5.00131 12.5471C5.01381 12.7678 5.11346 12.9745 5.27834 13.1217C6.60156 14.2521 8.26185 14.9126 10 15C11.7382 14.9126 13.3984 14.2521 14.7217 13.1217ZM5 8.33334C5 9.16667 5.74584 9.16667 6.66667 9.16667C7.5875 9.16667 8.33334 9.16667 8.33334 8.33334C8.33334 7.89131 8.15774 7.46739 7.84518 7.15483C7.53262 6.84227 7.1087 6.66667 6.66667 6.66667C6.22464 6.66667 5.80072 6.84227 5.48816 7.15483C5.1756 7.46739 5 7.89131 5 8.33334ZM11.6667 8.33334C11.6667 9.16667 12.4125 9.16667 13.3333 9.16667C14.2542 9.16667 15 9.16667 15 8.33334C15 7.89131 14.8244 7.46739 14.5118 7.15483C14.1993 6.84227 13.7754 6.66667 13.3333 6.66667C12.8913 6.66667 12.4674 6.84227 12.1548 7.15483C11.8423 7.46739 11.6667 7.89131 11.6667 8.33334Z"
                                        fill="#F9BD23" />
                                </g>
                                <defs>
                                    <clipPath id="clip0_7224_10484">
                                        <rect width="20" height="20" fill="white" />
                                    </clipPath>
                                </defs>
                            </svg>
                        </label>
                    </div>
                    <label class="w-0 flex-grow-1 uploaded-file-container">
                        <textarea class="form-control pt-3 radius-left-button pl-105px" id="msgInputValue" name="message" type="text"
                            placeholder="{{ translate('send_a_message') }}" aria-label="Search"></textarea>
                        <div class="d-flex justify-content-between items-container">
                            <div class="overflow-x-auto pt-3 pb-2">
                                <div>
                                    <div class="d-flex gap-3">
                                        <div class="d-flex gap-3 image-array flex-wrap"></div>
                                        <div class="d-flex gap-3 file-array flex-wrap"></div>
                                        <div class="d-flex gap-3 input-uploaded-file flex-wrap">
                                        </div>
                                    </div>
                                </div>
                                <div id="selected-files-container"></div>
                                <div id="selected-image-container"></div>
                            </div>
                        </div>
                    </label>
                    <button class="btn send-btn fw-bold fs-24 px-4 typing" type="submit" id="msgSendBtn" disabled
                        data-channel-id="{{ $channelId }}" data-driver-id="{{ $driver->id }}">
                        <i class="tio-send"></i>
                    </button>
                    <div id="circle-progress-2" class="circle-progress-2 ml-auto" style="display: none">
                        <div class="inner">
                            <div class="text title-color opacity-75">
                                <span class="progress-text"> uploading <span class="file-count">0</span>
                                    files</span>
                                <svg id="svg" width="30" height="30" viewport="0 0 12 12"
                                    version="1.1" xmlns="http://www.w3.org/2000/svg">
                                    <circle id="bar" r="10" cx="12" cy="12" fill="transparent"
                                        stroke-dasharray="100" stroke-dashoffset="0">
                                    </circle>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Files Uploaded state ends --}}
@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/js-zip/jszip.min.js') }}"></script>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/js-zip/FileSaver.min.js') }}"></script>



@endpush
