@php
    $taskAction = $task->exists ? route('follow-ups.update', [$meeting, $task]) : route('follow-ups.store', $meeting);
    $taskFormIdentity = parse_url($taskAction, PHP_URL_PATH).':'.($task->exists ? 'PATCH' : 'POST');
    $isSubmittedTask = old('_form') === $taskFormIdentity;
@endphp
<form method="post" action="{{ $taskAction }}">@csrf
<input type="hidden" name="_form" value="{{ $taskFormIdentity }}">
@if($task->exists)@method('PATCH')
@endif
<label for="task-{{ $task->id ?? 'new' }}">Tugas *</label><textarea id="task-{{ $task->id ?? 'new' }}" name="task" required rows="3">{{ $isSubmittedTask ? old('task', $task->task) : $task->task }}</textarea>
<div class="form-grid"><div><label for="pic-{{ $task->id ?? 'new' }}">Penanggung jawab *</label><select id="pic-{{ $task->id ?? 'new' }}" name="user_id" required>
@foreach($meeting->participants as $participant)
@continue($participant->user->role === 'client')<option value="{{ $participant->user_id }}" @selected(($isSubmittedTask ? old('user_id', $task->user_id) : $task->user_id) == $participant->user_id)>{{ $participant->user->name }}</option>
@endforeach
@if($task->exists && $task->user->role !== 'client' && ! $meeting->participants->contains('user_id', $task->user_id))<option value="{{ $task->user_id }}" @selected(($isSubmittedTask ? old('user_id', $task->user_id) : $task->user_id) == $task->user_id)>{{ $task->user->name }}</option>
@endif</select></div>
<div><label for="deadline-{{ $task->id ?? 'new' }}">Deadline *</label><input id="deadline-{{ $task->id ?? 'new' }}" type="date" name="deadline" required value="{{ $isSubmittedTask ? old('deadline', $task->deadline?->toDateString()) : $task->deadline?->toDateString() }}" min="{{ $meeting->starts_at->toDateString() }}"></div></div>
<label for="task-status-{{ $task->id ?? 'new' }}">Status</label><select id="task-status-{{ $task->id ?? 'new' }}" name="status">
@foreach(App\Models\FollowUp::STATUSES as $value => $label)<option value="{{ $value }}" @selected(($isSubmittedTask ? old('status', $task->status ?? 'pending') : ($task->status ?? 'pending')) === $value)>{{ $label }}</option>
@endforeach</select>
<div class="form-actions"><button class="button" type="submit">{{ $task->exists ? 'Simpan perubahan tugas' : 'Tambahkan tugas' }}</button></div>
</form>
