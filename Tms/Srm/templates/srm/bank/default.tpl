{% extends "master.tpl" %}

{% block main %}
  {% set displayFooter = 0 %}
  <div class="wrapper">
    <h1>金融機関管理</h1>
    {% for unit in banks %}
      {% if loop.first %}
        <table class="nlist">
          <thead>
            <tr>
              <td>コード</td>
              <td>店番</td>
              <td>金融機関名</td>
              <td>支店名</td>
              <td>区分</td>
              <td>口座番号</td>
              <td>口座名義人</td>
              <td> </td>
              <td> </td>
            </tr>
          </thead>
          <tbody>
      {% endif %}
      <tr>
        <td class="numeric-cell">{{ unit.bank_code }}</td>
        <td class="numeric-cell">{{ unit.branch_code }}</td>
        <td>{{ unit.bank }}</td>
        <td>{{ unit.branch }}</td>
        <td>{{ unit.account_type }}</td>
        <td class="numeric-cell">{{ unit.account_number }}</td>
        <td class="spacing-50-cell">{{ unit.account_holder }}</td>
        {% if apps.hasPermission('srm.bank.update') %}
          <td class="button"><a href="?mode=srm.bank.response:edit&id={{ unit.id|url_encode }}">編集</a></td>
        {% else %}
          <td class="button">&nbsp;</td>
        {% endif %}
        {% if apps.hasPermission('srm.bank.delete') %}
          <td class="button reddy"><label><input type="radio" name="delete" value="{{ unit.id }}"><span>削除</span></label></td>
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
      <p>利用できる金融機関は見つかりません。</p>
    {% endfor %}
    {% if apps.hasPermission('srm.bank.create') %} 
      <p class="create function-key"><a href="?mode=srm.bank.response:edit"><mark>＋</mark>金融機関を追加</a></p>
    {% endif %}
    {% if displayFooter == 1 %}
      <div class="form-footer">
        <input type="submit" name="s1_submit" value="実行">
        <input type="hidden" name="mode" value="srm.bank.receive:remove">
      </div>
    {% endif %}
  </div>
{% endblock %}
