{{--
    Renders one collapsible card per permission category.

    Required variables:
    - $categoryName (string)         : e.g. "Dashboard", "Reports", "System"
    - $groups (array)                : permission groups inside this category,
                                       shape matches RoleService::buildPermissionGroup()
    - $cardIndex (int)               : 0-based index used to build unique
                                       collapse IDs so Bootstrap accordions
                                       do not collide across cards
    - $preCheck (bool)               : true for edit/duplicate flows where the
                                       form must pre-select already-granted
                                       permissions; false for the create flow
    - $allowed_permissions (array)   : keyed by permission id, provided by
                                       RoleService::getAllowedPermissions()
                                       (only consulted when $preCheck = true)

    The card markup mirrors the legacy Dashboard/General/Reports cards
    exactly so FormValidation.checkMyModule / checkMyParent JS keeps
    working without change.
--}}
@php
    $collapseWrapperId = 'permission-card-collapse-' . $cardIndex;
    $collapseBodyId = 'permission-card-body-' . $cardIndex;
@endphp

<div class="card card-custom gutter-b example example-compact">
    <div class="card-body">
        <div class="accordion accordion-light accordion-light-borderless accordion-svg-toggle" id="{{ $collapseWrapperId }}">
            <div class="card">
                <div class="card-header collapse-header">
                    <div class="card-title" data-toggle="collapse" data-target="#{{ $collapseBodyId }}">
                        <span class="svg-icon svg-icon-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <polygon points="0 0 24 0 24 24 0 24" />
                                    <path d="M12.2928955,6.70710318 C11.9023712,6.31657888 11.9023712,5.68341391 12.2928955,5.29288961 C12.6834198,4.90236532 13.3165848,4.90236532 13.7071091,5.29288961 L19.7071091,11.2928896 C20.085688,11.6714686 20.0989336,12.281055 19.7371564,12.675721 L14.2371564,18.675721 C13.863964,19.08284 13.2313966,19.1103429 12.8242777,18.7371505 C12.4171587,18.3639581 12.3896557,17.7313908 12.7628481,17.3242718 L17.6158645,12.0300721 L12.2928955,6.70710318 Z" fill="#000000" fill-rule="nonzero" />
                                    <path d="M3.70710678,15.7071068 C3.31658249,16.0976311 2.68341751,16.0976311 2.29289322,15.7071068 C1.90236893,15.3165825 1.90236893,14.6834175 2.29289322,14.2928932 L8.29289322,8.29289322 C8.67147216,7.91431428 9.28105859,7.90106866 9.67572463,8.26284586 L15.6757246,13.7628459 C16.0828436,14.1360383 16.1103465,14.7686056 15.7371541,15.1757246 C15.3639617,15.5828436 14.7313944,15.6103465 14.3242754,15.2371541 L9.03007575,10.3841378 L3.70710678,15.7071068 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" transform="translate(9.000003, 11.999999) rotate(-270.000000) translate(-9.000003, -11.999999)" />
                                </g>
                            </svg>
                        </span>
                        <div class="card-label pl-4">{{ $categoryName }} Permissions</div>
                    </div>
                </div>
                <div id="{{ $collapseBodyId }}" class="collapse show" data-parent="#{{ $collapseWrapperId }}">
                    @foreach($groups as $permission)
                        @php
                            // Legacy Dashboard variant hides the parent checkbox and only
                            // shows children. Use it only when the parent actually has
                            // children — otherwise the row renders invisibly (see
                            // `doctor_dashboard`, which has no children).
                            $useHiddenParentVariant = $categoryName === 'Dashboard' && !empty($permission['children']);
                        @endphp
                        <div class="form-group row">
                            <label class="col-2 col-form-label"><strong>{{ $permission['title'] }}</strong></label>

                            @if($useHiddenParentVariant)
                                {{-- Dashboard uses the legacy hidden-parent / visible-children pattern --}}
                                <input id="allow_{{ $permission['name'] }}" type="checkbox" name="permission[]"
                                       class="allow_all allow {{ $permission['name'] }} allow_{{ $permission['name'] }}"
                                       value="{{ $permission['name'] }}"
                                       @if($preCheck && isset($allowed_permissions[$permission['id']])) checked="true" @endif
                                       style="visibility: hidden;"
                                       onclick="FormValidation.checkMyModule(this,'allow_{{ $permission['name'] }}');">
                                <div class="col-9 col-form-label">
                                    <div class="checkbox-inline">
                                        @foreach($permission['children'] as $child)
                                            <label class="checkbox permission_checkbox">
                                                <input id="sub-allow_{{ $child['name'] }}"
                                                       type="checkbox" name="permission[]"
                                                       class="allow_all allow {{ $permission['name'] }}  sub-allow_{{ $permission['name'] }}"
                                                       value="{{ $child['name'] }}"
                                                       @if($preCheck && isset($allowed_permissions[$child['id']])) checked="true" @endif
                                                       onclick="FormValidation.checkMyParent(this,'allow_{{ $permission['name'] }}' , 'sub-allow_{{ $permission['name'] }}', '{{ $child['name'] }}' );">
                                                <span></span>{{ $child['title'] }}</label>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="col-9 col-form-label">
                                    <div class="checkbox-inline">
                                        <label class="checkbox permission_checkbox">
                                            <input id="allow_{{ $permission['name'] }}" type="checkbox" name="permission[]"
                                                   class="allow_all allow {{ $permission['name'] }} allow_{{ $permission['name'] }}"
                                                   value="{{ $permission['name'] }}"
                                                   @if($preCheck && isset($allowed_permissions[$permission['id']])) checked="true" @endif
                                                   onclick="FormValidation.checkMyModule(this,'allow_{{ $permission['name'] }}');">
                                            <span></span>Display
                                        </label>
                                        @foreach($permission['children'] as $child)
                                            <label class="checkbox permission_checkbox">
                                                <input id="sub-allow_{{ $child['name'] }}"
                                                       type="checkbox" name="permission[]"
                                                       class="allow_all allow {{ $permission['name'] }}  sub-allow_{{ $permission['name'] }}"
                                                       value="{{ $child['name'] }}"
                                                       @if($preCheck && isset($allowed_permissions[$child['id']])) checked="true" @endif
                                                       onclick="FormValidation.checkMyParent(this,'allow_{{ $permission['name'] }}' , 'sub-allow_{{ $permission['name'] }}', '{{ $child['name'] }}' );">
                                                <span></span>{{ $child['title'] }}</label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
