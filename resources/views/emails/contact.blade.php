<p>
Name: {{ $data['name'] }}<br>
Email: {{ $data['email'] }}<br>
@if (!empty($data['phone']))
Phone: {{ $data['phone'] }}<br>
@endif
@if (!empty($data['subject']))
Subject: {{ $data['subject'] }}<br>
@endif
Message: {{ $data['message'] }}
</p>
