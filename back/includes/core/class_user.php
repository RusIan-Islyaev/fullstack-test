<?php

class User {

    // GENERAL

    public static function user_info($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [];
        // info
        $q = DB::query("SELECT user_id, phone, access FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access']
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0
            ];
        }
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    public static function users_list($d = []) {
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];
        
        // where
        $where = [];
        if ($search) {
            $where[] = "(first_name LIKE '%".$search."%' OR last_name LIKE '%".$search."%' OR phone LIKE '%".$search."%' OR email LIKE '%".$search."%')";
        }
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        
        // info
        $q = DB::query("SELECT user_id, first_name, last_name, phone, email, last_login, plot_id
            FROM users ".$where." ORDER BY user_id DESC LIMIT ".$offset.", ".$limit.";") or die (DB::error());
        
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'phone_str' => phone_formatting($row['phone']),
                'email' => $row['email'],
                'plot_ids' => $row['plot_id'],
                'last_login' => $row['last_login'] ? date('Y/m/d H:i', $row['last_login']) : 'Never'
            ];
        }
        
        // paginator
        $q = DB::query("SELECT count(*) FROM users ".$where.";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search='.$search;
        paginator($count, $offset, $limit, $url, $paginator);
        
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function users_fetch($d = []) {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    public static function user_info_full($user_id) {
        $q = DB::query("SELECT user_id, first_name, last_name, phone, email, plot_id FROM users WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'plot_ids' => $row['plot_id']
            ];
        } else {
            return [
                'id' => 0,
                'first_name' => '',
                'last_name' => '',
                'phone' => '',
                'email' => '',
                'plot_ids' => ''
            ];
        }
    }

    // ACTIONS

    public static function user_edit_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_info_full($user_id));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = []) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        
        // Validate and clean data
        $validation = User::validate_user_data($d, $user_id);
        
        // If there are errors, return them
        if (!empty($validation['errors'])) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }
        
        $data = $validation['cleaned_data'];
        
        // update
        if ($user_id) {
            $set = [];
            $set[] = "first_name='".$data['first_name']."'";
            $set[] = "last_name='".$data['last_name']."'";
            $set[] = "phone='".$data['phone']."'";
            $set[] = "email='".$data['email']."'";
            $set[] = "plot_id='".$data['plot_ids']."'";
            $set[] = "updated='".Session::$ts."'";
            $set = implode(", ", $set);
            DB::query("UPDATE users SET ".$set." WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
        } else {
            DB::query("INSERT INTO users (
                first_name,
                last_name,
                phone,
                email,
                plot_id,
                updated
            ) VALUES (
                '".$data['first_name']."',
                '".$data['last_name']."',
                '".$data['phone']."',
                '".$data['email']."',
                '".$data['plot_ids']."',
                '".Session::$ts."'
            );") or die (DB::error());
            $user_id = DB::connect()->lastInsertId();
        }
        
        // output
        return User::users_fetch(['offset' => $offset]);
    }

    public static function user_delete($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        
        if ($user_id) {
            DB::query("DELETE FROM users WHERE user_id='".$user_id."' LIMIT 1;") or die (DB::error());
        }
        
        // output
        return User::users_fetch(['offset' => $offset]);
    }

    public static function validate_user_data($d, $user_id = 0) {
        $errors = [];
        
        // Get and clean data
        $first_name = isset($d['first_name']) && trim($d['first_name']) ? trim($d['first_name']) : '';
        $last_name = isset($d['last_name']) && trim($d['last_name']) ? trim($d['last_name']) : '';
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : '';
        $email = isset($d['email']) ? strtolower(trim($d['email'])) : '';
        
        // Required fields validation (except plot_ids)
        if (!$first_name) $errors['first_name'] = 'First name is required';
        if (!$last_name) $errors['last_name'] = 'Last name is required';
        if (!$phone) $errors['phone'] = 'Phone is required';
        if (!$email) $errors['email'] = 'Email is required';
        
        // Email format validation
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        }
        
        return [
            'errors' => $errors,
            'cleaned_data' => [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone,
                'email' => $email,
                'plot_ids' => isset($d['plot_ids']) ? trim($d['plot_ids']) : ''
            ]
        ];
    }

}
