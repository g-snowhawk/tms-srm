{% extends "master.tpl" %}

{% block main %}
  {% set displayFooter = 0 %}
  <input type="hidden" name="mode" value="srm.client.receive:remove">
  <div class="wrapper">
    <h1>取引先一覧</h1>
    {% for unit in users %}
      {% if loop.first %}
        <table class="nlist">
          <thead>
            <tr>
              <td>ID</td>
              <td>会社名</td>
              <td>担当者</td>
              <td> </td>
              <td> </td>
            </tr>
          </thead>
          <tbody>
      {% endif %}
      <tr>
        <td>{{ unit.id }}</td>
        <td>{{ unit.company }}</td>
        <td>{{ unit.fullname}}</td>
        {% if apps.hasPermission('srm.client.update') %}
          <td class="button"><a href="?mode=srm.client.response:edit&id={{ unit.id|url_encode }}">編集</a></td>
        {% else %}
          <td class="button">&nbsp;</td>
        {% endif %}
        {% if apps.hasPermission('srm.client.delete') %}
          <td class="button reddy"><label><input type="radio" name="delete" value="{{ unit.id }}">削除</label></td>
        {% else %}
          <td class="button reddy">&nbsp;</td>
        {% endif %}
      </tr>
      {% if loop.last %}
          </tbody>
        </table>
        {% set displayFooter = 1 %}
      {% endif %}
    {% else %}
      <p>未だ取引先の登録がありません</p>
    {% endfor %}
    {% if apps.hasPermission('srm.client.create') %}
      <p class="create function-key"><a href="?mode=srm.client.response:edit"><mark>＋</mark>新規取引先</a></p>
    {% endif %}
    {% if displayFooter == 1 %}
      <div class="form-footer">
        <input type="submit" name="s1_submit" value="実行">
      </div>
    {% endif %}
  </div>
{% endblock %}
