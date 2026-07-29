<option value="">{{ translate('Select a category') }}</option>
@foreach($activeBlogCategories as $key => $activeBlogCategory)
    <option value="{{ $activeBlogCategory->id }}"
        {{ old('blog_category_id', $data->blog_category_id ?? null) == $activeBlogCategory->id ? 'selected' : '' }}>
        {{ $activeBlogCategory->name }}
    </option>
@endforeach
