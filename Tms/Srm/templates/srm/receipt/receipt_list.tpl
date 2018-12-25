{% extends "master.tpl" %}

{% block main %}
  <div class="wrapper">
    <h1>帳票一覧</h1>
    {% for unit in receipts %}
      {% if loop.first %}
        <ul class="button-list">
      {% endif %}
      <li><a href="?t={{ unit.id }}">{{ unit.title }}</a></li>
      {% if loop.last %}
        </ul>
      {% endif %}
    {% else %}
      <p>未だ帳票が登録されていません</p>
    {% endfor %}
  </div>
{% endblock %}
