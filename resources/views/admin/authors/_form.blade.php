@if ($errors->any())
    <div class="adm-err">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="adm-field">
    <label for="author-name">Name</label>
    <input id="author-name" type="text" name="name" value="{{ old('name', isset($author) ? $author->name : '') }}">
</div>

<div class="adm-field">
    <label for="author-description">Description</label>
    <textarea id="author-description" name="description" rows="4">{{ old('description', isset($author) ? $author->description : '') }}</textarea>
</div>

<div class="adm-field">
    <label for="author-picture">Picture</label>
    @if (isset($author) && $author->picture)
        <img class="adm-field__preview is-round" src="{{ $author->picture }}" alt="">
        <span class="adm-field__hint"><label><input type="checkbox" name="remove_picture" value="1"> Remove picture</label></span>
    @endif
    <input id="author-picture" type="file" name="picture" accept="image/*">
    <span class="adm-field__hint">{{ isset($author) && $author->picture ? 'Replaces the current picture.' : 'Cropped to a square (400×400).' }}</span>
</div>
