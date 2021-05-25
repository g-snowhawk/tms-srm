{% extends "master.tpl" %}

{% block main %}
  {% set displayFooter = 0 %}
  <input type="hidden" name="mode" value="srm.template.receive:remove">
  <div class="wrapper">
    <h1>帳票テンプレート一覧</h1>
    {% for unit in templates %}
      {% if loop.first %}
        <table class="nlist">
          <thead>
            <tr>
              <td>No</td>
              <td>帳票名</td>
              <td>行数</td>
              <td>&nbsp;</td>
              <td>&nbsp;</td>
            </tr>
          </thead>
          <tbody>
      {% endif %}
      <tr>
        <td class="numeric-cell">{{ unit.priority }}</td>
        <td class="spacing-75-cell">{{ unit.title }}</td>
        <td class="spacing-25-cell alphabet-cell">{{ unit.line }}</td>
        {% if apps.hasPermission('srm.template.update') %}
          <td class="button"><a href="?mode=srm.template.response:edit&id={{ unit.id|url_encode }}">編集</a></td>
        {% else %}
          <td class="button">&nbsp;</td>
        {% endif %}
        {% if apps.hasPermission('srm.template.delete') %}
          <td class="button reddy">{% if unit.kind != 1 %}<label><input type="radio" name="delete" value="{{ unit.id }}"><span>削除</span></label>{% else %}&nbsp;{% endif %}</td>
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
      <p>未だ帳票テンプレートの登録がありません</p>
    {% endfor %}
    {% if apps.hasPermission('srm.template.create') %}
      <p class="create function-key"><a href="?mode=srm.template.response:edit"><mark>＋</mark>新規帳票テンプレート</a></p>
    {% endif %}
    {% if displayFooter == 1 %}
      <div class="form-footer">
        <input type="submit" name="s1_submit" value="実行">
      </div>
    {% endif %}
  </div>
{% endblock %}
