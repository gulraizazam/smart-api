<div class="row">
    <div class="form-group col-md-6">
        <label>Discount Type*</label>
        <div class="input-group">
            <div class="icheck-list">
                <label>
                    <input type="radio" name="discount_type" value="{{Config::get('constants.Service')}}" checked
                           class="icheck"> Treatment </label>
                <label>
                    <input type="radio" name="discount_type" value="{{Config::get('constants.Consultancy')}}"
                           class="icheck"> Consultancy </label>
            </div>
        </div>
    </div>
    <div class="form-group col-md-6">
        <label>Group*</label>
        <div class="input-group">
            <div class="icheck-list" id="slug">
                <label>
                    <input type="radio" name="slug" value="default" checked> Default </label>
                <label>
                    <input type="radio" name="slug" value="custom"> Custom </label>
                <label>
                    <input type="radio" name="slug" value="birthday"> Birthday </label>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="form-group col-md-6">
        {!! Form::label('name', 'Name*', ['class' => 'control-label']) !!}
        {!! Form::text('name', old('name'), ['class' => 'form-control inpt-focus', 'placeholder' => '', 'required' => '']) !!}
        @if($errors->has('name'))
            <p class="help-block">
                {{ $errors->first('name') }}
            </p>
        @endif
    </div>
    <div class=" form-group col-md-6">
        {!! Form::label('type', 'Type*', ['class' => 'control-label']) !!}
        <select name="type" class="form-control" id="amounttype" required>
            <option value="">Select Amount Type</option>
            <option value="Fixed">Fixed</option>
            <option value="Percentage">Percentage</option>
        </select>
        @if($errors->has('type'))
            <p class="help-block">
                {{ $errors->first('type') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="form-group col-md-12">
        {!! Form::label('amount', 'Amount*', ['class' => 'control-label']) !!}
        {!! Form::Number('amount', old('amount'), ['class' => 'form-control', 'placeholder' => '', 'required' => '','id'=>'amountF','min'=>'0','max'=>'']) !!}
        @if($errors->has('amount'))
            <p class="help-block">
                {{ $errors->first('amount') }}
            </p>
        @endif
    </div>
</div>
<div class="row" id="days_range" style="display: none;">
    <div class="col-md-6 form-group">
        {!! Form::label('pre_days', 'Pre Days', ['class' => 'control-label']) !!}
        {!! Form::Number('pre_days', old('pre_days'), ['class' => 'form-control', 'placeholder' => '','min'=>'0']) !!}
        @if($errors->has('amount'))
            <p class="help-block">
                {{ $errors->first('amount') }}
            </p>
        @endif
    </div>
    <div class="col-md-6 form-group">
        {!! Form::label('Post_days', 'Post Days', ['class' => 'control-label']) !!}
        {!! Form::Number('post_days', old('post_days'), ['class' => 'form-control', 'placeholder' => '','min'=>'0']) !!}
        @if($errors->has('amount'))
            <p class="help-block">
                {{ $errors->first('amount') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="form-group col-md-6">
        {!! Form::label('start', 'From', ['class' => 'control-label']) !!}
        {!! Form::text('start',old('start') ? \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d'):old('start'), ['class' => 'form-control date_to_rota','required' => '','readonly'=>'true']) !!}
        @if($errors->has('start'))
            <p class="help-block">
                {{ $errors->first('start') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-6">
        {!! Form::label('end', 'To', ['class' => 'control-label']) !!}
        {!! Form::text('end',old('end') ? \Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d'):old('end'), ['class' => 'form-control date_to_rota','required' => '', 'readonly'=>'true']) !!}
        @if($errors->has('end'))
            <p class="help-block">
                {{ $errors->first('end') }}
            </p>
        @endif
    </div>

</div>
<div class="row">
    <div class="form-group col-md-12">
        <label class="mt-checkbox mt-checkbox-outline">
        <!-- {!! Form::label('active', '', ['class' => 'mt-checkbox mt-checkbox-outline']) !!} -->
            {!! Form::checkbox('active', '1')!!} Active
            <span></span>
            @if($errors->has('active'))
                <p class="help-block">
                    {{ $errors->first('active') }}
                </p>
            @endif
        </label>
    </div>
</div>
<div class="clearfix"></div>
<script>
    {{--Jquery function for check value enter in percentage or fixed--}}
    $(document).ready(function () {
        $("#amounttype").change(function () {
            var amounttype = $("#amounttype").val();
            if (amounttype == 'Fixed') {
                $("#amountF").removeAttr("max");
            }
            if (amounttype == 'Percentage') {
                $("#amountF").attr('max', 100);
            }
        });
        $("#amounttype").change();
    });
    //End
    {{--Jquery function for set default date in datepicker--}}
    $(document).ready(function () {
        var date = new Date();
        date.setDate(date.getDate());
        $('.date_to_rota').datepicker({
            format: 'yyyy-mm-dd',
            startDate: date
        }).on('changeDate', function (ev) {
            $(this).datepicker('hide');
        })
        $('.date_to_rota').datepicker({dateFormat: 'dd-mm-yy'}).datepicker("setDate", new Date());
    });
    //End
    /*Function for radio button to show and hide pre and post input*/
    $(function () {
        $('#slug input[type=radio]').change(function () {
            if ($(this).is(':checked')) {
                var title = "Fixed";
                var slug_type = $(this).val();
                if (slug_type == 'birthday') {
                    $("#days_range").show();
                    $("#amounttype option[value=" + title + "]").show();
                } else if (slug_type == 'custom') {
                    $("#days_range").hide();
                    $("#amounttype option[value=" + title + "]").hide();
                } else {
                    $("#days_range").hide();
                    $("#amounttype option[value=" + title + "]").show();
                }
            }
        });
    });
</script>

