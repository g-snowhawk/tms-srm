{% set formposition = 'bottom' %}
{% extends "subform.tpl" %}

{% block main %}
<article class="wrapper">
  <h1>検索オプション</h1>
  <div class="flex">
    <div class="column">
      <div class="fieldset">
        <label for="issue_date">日付指定</label>
        <div class="input flex">
          <input type="date" name="issue_date_start" id="issue_date_start" value="{{ post.issue_date_start is empty ? '' : post.issue_date_start|date('Y-n-j') }}" class="datetime grow-1">
          <em>〜</em>
          <input type="date" name="issue_date_end" id="issue_date_end" value="{{ post.issue_date_end is empty ? '' : post.issue_date_end|date('Y-n-j') }}" class="datetime grow-1">
        </div>
      </div>
      <div class="naked">
        <label><input type="radio" name="andor" id="andor-1" value="AND"{% if post.andor == "AND" %} checked{% endif %}>AND検索</label>
        <label><input type="radio" name="andor" id="andor-2" value="OR"{% if post.andor == "OR" %} checked{% endif %}>OR検索</label>
      </div>
    </div>
    <div class="column">
    </div>
  </div>

  <div class="form-footer">
    <input type="hidden" name="mode" value="srm.receipt.receive:save-search-options">
    <input type="submit" name="s1_submit" value="保存">
    <input type="submit" name="s1_clear" value="クリア">
  </div>
</article>
{% endblock %}
