<select name="machine_type_id" id="machine_type_id" class="form-control select2" required>
    <option value="">Select Machine Type</option>
    @foreach($machinetypes as $machine)
        <option value="{{$machine->id}}">{{$machine->name}}</option>
    @endforeach
</select>