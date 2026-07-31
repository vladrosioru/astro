@if ($errors->any())
    <div class="form-errors">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<p><label>Name
    <input type="text" name="name" value="{{ old('name', isset($author) ? $author->name : '') }}">
</label></p>

<p><label>Description
    <textarea name="description" rows="4">{{ old('description', isset($author) ? $author->description : '') }}</textarea>
</label></p>

<fieldset>
    <legend>Picture</legend>
    @if (isset($author) && $author->picture)
        <p><img src="{{ $author->picture }}" alt="" style="max-width:160px;height:auto;display:block;border-radius:50%"></p>
        <p><label><input type="checkbox" name="remove_picture" value="1"> Remove picture</label></p>
    @endif
    <p><label>{{ isset($author) && $author->picture ? 'Replace image' : 'Upload image' }}
        <input type="file" name="picture" accept="image/*"></label></p>
    <p class="muted">Cropped to a square (400&times;400).</p>
</fieldset>
