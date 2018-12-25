{% extends "master.tpl" %}

{% block main %}
  <p id="backlink"><a href="?mode=srm.bank.response">一覧に戻る</a></p>
  <div class="wrapper">
    <h1>金融機関情報編集</h1>
    {% if err.vl_bank_code == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% elseif err.vl_bank_code == 2 %}
      <div class="error">
        <i>数字のみ入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_bank_code == 1 %} invalid{% endif %}">
      <label for="code">金融機関コード</label>
      <input type="text" name="bank_code" id="bank_code" maxlength="4" value="{{ post.bank_code }}">
    </div>
    {% if err.vl_branch_code == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% elseif err.vl_branch_code == 2 %}
      <div class="error">
        <i>数字のみ入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_branch_code == 1 %} invalid{% endif %}">
      <label for="account_holder">店番</label>
      <input type="text" name="branch_code" id="branch_code" maxlength="3" value="{{ post.branch_code }}">
    </div>
    {% if err.vl_bank == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_bank == 1 %} invalid{% endif %}">
      <label for="bank">金融機関名</label>
      <input type="text" name="bank" id="bank" value="{{ post.bank }}">
    </div>
    {% if err.vl_branch == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_branch == 1 %} invalid{% endif %}">
      <label for="branch">支店名</label>
      <input type="text" name="branch" id="branch" value="{{ post.branch }}">
    </div>
    <div class="fieldset">
      <label for="account_type">区分</label>
      <select name="account_type" id="account_type">
        <option value="普通預金"{% if post.account_type == "普通預金" %} selected{% endif %}>普通預金</option>
        <option value="当座預金"{% if post.account_type == "当座預金" %} selected{% endif %}>当座預金</option>
        <option value="定期預金"{% if post.account_type == "定期預金" %} selected{% endif %}>定期預金</option>
      </select>
    </div>
    {% if err.vl_code == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% elseif err.vl_code == 2 %}
      <div class="error">
        <i>数字のみ入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_code == 1 %} invalid{% endif %}">
      <label for="account_number">口座番号</label>
      <input type="text" name="account_number" id="account_number" maxlength="7" value="{{ post.account_number }}">
    </div>
    {% if err.vl_account_holder == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_account_holder == 1 %} invalid{% endif %}">
      <label for="account_holder">口座名義人</label>
      <input type="text" name="account_holder" id="account_holder" maxlength="32" value="{{ post.account_holder }}">
    </div>
    <div class="metadata">
      登録日：{{ post.create_date|date('Y年n月j日 H:i') }}<input type="hidden" name="create_date" value="{{ post.create_date }}"><br>
      更新日：{{ post.modify_date|date('Y年n月j日 H:i') }}<input type="hidden" name="modify_date" value="{{ post.modify_date }}"><br>
    </div>
    <div class="form-footer">
      <input type="submit" name="s1_submit" value="登録">
      <input type="hidden" name="id" value="{{ post.id }}">
      <input type="hidden" name="mode" value="srm.bank.receive:save">
    </div>
  </div>
{% endblock %}
