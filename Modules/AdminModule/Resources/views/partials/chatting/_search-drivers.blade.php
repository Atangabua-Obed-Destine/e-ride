<div class="inbox_chat d-flex flex-column">
    <div class="list_filter max-h-100vh-220 overflow-y-auto pb-4">
        @forelse($driverList as $driver)
            <div class="chat_list p-3 d-flex gap-2  bg-soft-secondary driver-conversation"
                 data-channel-id="{{ $driver?->channelUsersToAdmin && $driver?->channelUsersToAdmin->count() > 0 ? $driver?->channelUsersToAdmin[0]?->channel_id : '' }}"
                 data-driver-id="{{ $driver?->id }}">
                <div class="chat_people media gap-10 w-100" id="chat_people">
                    <div class="avatar avatar-sm chat_img rounded-circle position-relative">
                        <img src="{{ onErrorImage(
                                                    $driver?->profile_image,
                                                    dynamicStorage('storage/app/public/driver/profile') . '/' . $driver?->profile_image,
                                                    dynamicAsset('public/assets/admin-module/img/user.png'),
                                                    'driver/profile/',
                                                ) }}"
                             id="" class="avatar-img rounded-circle aspect-1 h-100"
                             alt="">
                    </div>
                    <div class="chat_ib media-body title-color">
                        <h6 class="mb-1 seller active-text fw-semibold" id=""
                            data-name="{{ $driver?->full_name ?? ($driver?->first_name ? $driver?->first_name . ' ' . $driver?->last_name : 'N/A') }}"
                            data-phone="{{ $driver?->phone }}">
                            {{ $driver?->full_name ?? ($driver?->first_name ? $driver?->first_name . ' ' . $driver?->last_name : 'N/A') }}
                            <span
                                    class="fw-medium fs-10 float-end opacity-80">{{ $driver?->channelUsersToAdmin && $driver?->channelUsersToAdmin->count() > 0 && $driver?->channelUsersToAdmin[0]?->last_message ? formatCustomDate( $driver?->channelUsersToAdmin[0]?->last_message?->created_at ?? $driver?->channelUsersToAdmin[0]?->created_at) : '' }}</span>
                        </h6>
                        <div class="fs-12 opacity-50 d-block mb-2" id=""
                             data-name="Will Smith"
                             data-phone="{{ $driver?->phone }}">
                            {{ $driver?->phone }}</div>
                        <div class="d-flex justify-content-between align-items-center gap-10">
                                                    <span
                                                            class="fs-12 line--limit-1">{{ $driver?->channelUsersToAdmin && $driver?->channelUsersToAdmin->count() > 0 && $driver?->channelUsersToAdmin[0]?->last_message ? $driver?->channelUsersToAdmin[0]?->last_message?->message ?? translate('Shared file') : '' }}
                                                    </span>
                            <span
                                    class="new-msg-count {{ $driver?->channelUsersToAdmin && $driver?->channelUsersToAdmin->count() > 0 && $driver?->channelUsersToAdmin[0]?->is_unread_count ? ($driver?->channelUsersToAdmin[0]?->is_unread_count > 0 ? '' : 'd-none') : 'd-none' }}">{{ $driver?->channelUsersToAdmin && $driver?->channelUsersToAdmin->count() > 0 && $driver?->channelUsersToAdmin[0]?->is_unread_count ? $driver?->channelUsersToAdmin[0]?->is_unread_count : '' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="d-flex justify-content-center mt-2" id="">
                <div class="d-flex justify-content-center align-items-center h-100">
                    <div class="d-flex flex-column align-items-center gap-20">
                        <img width="38"
                             src="{{ dynamicAsset('public/assets/admin-module/img/svg/driver-man.svg') }}"
                             alt="">
                        <p class="fs-12">{{ translate('no driver found') }}</p>
                    </div>
                </div>
            </div>

        @endforelse
    </div>
</div>
