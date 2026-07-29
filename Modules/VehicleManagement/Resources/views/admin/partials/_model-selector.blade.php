<option value="select_model">{{ translate('Select Model') }}</option>
@foreach($models as $model)
     <option value="{{$model->id}}" {{isset($model_id) && $model_id == $model->id ? 'selected' : ''}}>{{$model->name}}</option>
@endforeach
