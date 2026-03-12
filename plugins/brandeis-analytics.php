<?php
/**
 * Plugin Name: Brandeis Research Analytics
 * Description: Proxy for Esploro, OpenAlex, LibInsight, LibCal, and Alma Analytics APIs.
 * Version: 3.4 (Hybrid Lineage Matching)
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Brandeis_Analytics_API {

    const BRANDEIS_ID = 'I6902469';
    const LIBCAL_CALENDAR_ID = 1222;
    
    // Cache Keys
    const OPENALEX_COLLAB_CACHE = 'brandeis_openalex_collab_v6';
    const OPENALEX_IMPACT_CACHE = 'brandeis_openalex_impact_v6';
    const OA_AVOIDANCE_CACHE    = 'brandeis_oa_avoidance_v12'; // Bumped to v12
    const LIBINSIGHT_CACHE      = 'brandeis_libinsight_events_v7';
    
    // LibCal keys
    const LIBCAL_TOKEN_CACHE = 'brandeis_libcal_token_v1';
    const LIBCAL_DATA_CACHE_PREFIX = 'brandeis_libcal_data_y'; 
    
    const CACHE_DURATION = 2592000; // 30 days
    const CACHE_WEEKLY = 604800;    // 1 week

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // --- 1. Esploro & Collections ---
        register_rest_route('brandeis/v1', '/research-analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_esploro_data'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('brandeis/v1', '/collections-expenditures', [
            'methods' => 'GET',
            'callback' => [$this, 'get_collections_data'],
            'permission_callback' => '__return_true',
            'args' => [
                'period' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return in_array($param, ['current', 'previous', 'two_ago']);
                    }
                ]
            ]
        ]);

        register_rest_route('brandeis/v1', '/collection-usage-profile', [
            'methods' => 'GET',
            'callback' => [$this, 'get_collection_usage_profile'],
            'permission_callback' => '__return_true',
        ]);

        // --- 2. OpenAlex Routes ---
        register_rest_route('brandeis/v1', '/openalex-collaborations', [
            'methods' => 'GET',
            'callback' => [$this, 'get_openalex_collaborations'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('brandeis/v1', '/citation-impact', [
            'methods' => 'GET',
            'callback' => [$this, 'get_citation_impact_data'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('brandeis/v1', '/oa-cost-avoidance', [
            'methods' => 'GET',
            'callback' => [$this, 'get_oa_cost_avoidance'],
            'permission_callback' => '__return_true',
        ]);

        // --- 3. LibInsight & LibCal ---
        register_rest_route('brandeis/v1', '/libinsight-events', [
            'methods' => 'GET',
            'callback' => [$this, 'get_libinsight_data'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('brandeis/v1', '/libcal-events', [
            'methods' => 'GET',
            'callback' => [$this, 'get_libcal_data'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ==========================================================
    // CONTROLLERS
    // ==========================================================

    /**
     * --- CONTROLLER 1: ESPLORO ANALYTICS ---
     */
    public function get_esploro_data() {
        if (!defined('ESPLORO_API_KEY')) return new WP_Error('missing_config', 'API KEY missing.');
        $apiKey = ESPLORO_API_KEY;
        $reportPath = urlencode("/shared/Esploro Brandeis University 01BRAND_INST/Reports/Mark Paris/Esploro Asset Count and Views (Non-ETD) -- Last 10 Years");
        $url = "https://api-na.hosted.exlibrisgroup.com/esploro/v1/researchanalytics/reports?path={$reportPath}&limit=1000&apikey={$apiKey}";

        $response = wp_remote_get($url, ['headers' => ['Accept' => 'application/xml'], 'timeout' => 45]);
        if (is_wp_error($response)) return $response;

        $xmlString = wp_remote_retrieve_body($response);
        $xmlString = str_replace('xmlns="urn:schemas-microsoft-com:xml-analysis:rowset"', '', $xmlString);

        try {
            $xml = simplexml_load_string($xmlString);
            $json = json_encode($xml);
            $array = json_decode($json, true);
            $rows = $array['QueryResult']['ResultXml']['rowset']['Row'] ?? [];
            return rest_ensure_response(array_map(function($row) {
                return ['year' => $row['Column1']??null, 'files_views' => $row['Column2']??0, 'asset_count' => $row['Column3']??0];
            }, $rows));
        } catch (Exception $e) { return []; }
    }

    /**
     * --- CONTROLLER 2: COLLECTIONS ANALYTICS ---
     */
    public function get_collections_data($request) {
        if (!defined('ESPLORO_API_KEY')) return new WP_Error('missing_config', 'API KEY missing.');
        
        $apiKey = ESPLORO_API_KEY;
        $reportPath = urlencode("/shared/Brandeis University/Reports/Mark Paris/MP Last Three Fiscal Years of Collections Expenditures");
        $period = $request['period'];
        
        $filterValue = "";
        switch ($period) {
            case 'current': $filterValue = "Current Fiscal Year"; break;
            case 'previous': $filterValue = "Previous Fiscal Year"; break;
            case 'two_ago': $filterValue = "Two Fiscal Years Ago"; break;
        }

        $filterXml = urlencode('<sawx:expr xsi:type="sawx:comparison" op="equal" xmlns:sawx="com.siebel.analytics.web/expression/v1.1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"> <sawx:expr xsi:type="sawx:sqlExpression">"Fiscal Period"."Fiscal Period Filter"</sawx:expr> <sawx:expr xsi:type="xsd:string">' . $filterValue . '</sawx:expr> </sawx:expr>');
        $url = "https://api-na.hosted.exlibrisgroup.com/almaws/v1/analytics/reports?path={$reportPath}&filter={$filterXml}&limit=1000&apikey={$apiKey}";

        $response = wp_remote_get($url, ['headers' => ['Accept' => 'application/xml'], 'timeout' => 45]);
        if (is_wp_error($response)) return $response;

        try {
            $xmlString = wp_remote_retrieve_body($response);
            $xmlString = str_replace('xmlns="urn:schemas-microsoft-com:xml-analysis:rowset"', '', $xmlString);
            $xml = simplexml_load_string($xmlString);
            
            $json = json_encode($xml);
            $array = json_decode($json, true);
            $rows = $array['QueryResult']['ResultXml']['rowset']['Row'] ?? [];
            
            if (isset($rows['Column0'])) { $rows = [$rows]; }

            return rest_ensure_response(array_map(function($row) {
                return [
                    'fiscal_period' => $row['Column1'] ?? '',
                    'fund_name' => $row['Column2'] ?? '',
                    'parent_fund' => $row['Column3'] ?? '',
                    'expenditure' => (float)($row['Column4'] ?? 0)
                ];
            }, $rows));
        } catch (Exception $e) { return new WP_Error('xml_error', $e->getMessage(), ['status' => 500]); }
    }

    /**
     * --- CONTROLLER 3: COLLECTION USAGE PROFILE ---
     */
    public function get_collection_usage_profile() {
        if (!defined('ESPLORO_API_KEY')) return new WP_Error('missing_config', 'API KEY missing.');
        $apiKey = ESPLORO_API_KEY;
        $reportPath = urlencode("/shared/Brandeis University/Reports/Mark Paris/Collection and Usage Profile");
        $url = "https://api-na.hosted.exlibrisgroup.com/almaws/v1/analytics/reports?path={$reportPath}&limit=1000&apikey={$apiKey}";

        $response = wp_remote_get($url, ['headers' => ['Accept' => 'application/xml'], 'timeout' => 45]);
        if (is_wp_error($response)) return $response;

        try {
            $xmlString = wp_remote_retrieve_body($response);
            $xmlString = str_replace('xmlns="urn:schemas-microsoft-com:xml-analysis:rowset"', '', $xmlString);
            $xml = simplexml_load_string($xmlString);
            
            $json = json_encode($xml);
            $array = json_decode($json, true);
            $rows = $array['QueryResult']['ResultXml']['rowset']['Row'] ?? [];
            
            if (isset($rows['Column0'])) { $rows = [$rows]; }

            return rest_ensure_response(array_map(function($row) {
                return [
                    'classification' => $row['Column1'] ?? '',
                    'item_count' => (float)($row['Column2'] ?? 0),
                    'loan_count' => (float)($row['Column3'] ?? 0)
                ];
            }, $rows));
        } catch (Exception $e) { return new WP_Error('xml_error', $e->getMessage(), ['status' => 500]); }
    }

    /**
     * --- CONTROLLER 4: OPENALEX COLLABORATIONS ---
     */
    public function get_openalex_collaborations() {
        if ($cached = get_transient(self::OPENALEX_COLLAB_CACHE)) {
            return rest_ensure_response($cached);
        }
        $url = 'https://api.openalex.org/works?filter=institutions.id:' . self::BRANDEIS_ID . ',publication_year:>2021&per-page=100&select=id,title,authorships,publication_year';
        $response = $this->fetch_openalex($url);
        if (is_wp_error($response)) return $response;

        $results = json_decode(wp_remote_retrieve_body($response), true)['results'] ?? [];
        $processedData = $this->process_collaborations($results);
        set_transient(self::OPENALEX_COLLAB_CACHE, $processedData, self::CACHE_DURATION);
        return rest_ensure_response($processedData);
    }

    /**
     * --- CONTROLLER 5: OPENALEX IMPACT ---
     */
    public function get_citation_impact_data() {
        if ($cached = get_transient(self::OPENALEX_IMPACT_CACHE)) {
            return rest_ensure_response($cached);
        }
        set_time_limit(120); 
        ini_set('memory_limit', '512M');

        $all_results = [];
        $base_url = 'https://api.openalex.org/works?filter=institutions.id:' . self::BRANDEIS_ID . ',type:article,publication_year:>2021,primary_topic.id:!null&per-page=100&select=id,title,publication_year,fwci,primary_topic,cited_by_count,authorships';

        $page = 1;
        $more_pages = true;
        while ($more_pages && $page <= 10) {
            $url = $base_url . "&page=" . $page;
            $response = $this->fetch_openalex($url);
            if (is_wp_error($response)) break; 
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $results = $data['results'] ?? [];
            if (!empty($results)) {
                $all_results = array_merge($all_results, $results);
                $page++; 
                usleep(100000); 
            } else { 
                $more_pages = false; 
            }
        }
        
        $processedData = [];
        foreach ($all_results as $work) {
            if (empty($work['primary_topic']) || empty($work['primary_topic']['subfield'])) continue;
            
            // Verify Brandeis Author
            $is_verified = false;
            foreach (($work['authorships']??[]) as $auth) {
                foreach ($auth['institutions'] as $inst) {
                    if (strpos($inst['id'], self::BRANDEIS_ID) !== false) { 
                        $is_verified = true; 
                        break 2; 
                    }
                }
            }
            if (!$is_verified) continue;

            $processedData[] = [
                'id' => $work['id'],
                'fwci' => (float)($work['fwci'] ?? 0),
                'subfield' => $work['primary_topic']['subfield']['display_name'] ?? 'General',
                'title' => substr($work['title'], 0, 100) . "...", 
                'year' => $work['publication_year'],
                'citations' => $work['cited_by_count'] ?? 0 
            ];
        }
        set_transient(self::OPENALEX_IMPACT_CACHE, array_values($processedData), self::CACHE_DURATION);
        return rest_ensure_response(array_values($processedData));
    }

    /**
     * --- CONTROLLER 6: OA COST AVOIDANCE (HYBRID LINEAGE + EXPLICIT) ---
     */
    public function get_oa_cost_avoidance() {
        if ($cached = get_transient(self::OA_AVOIDANCE_CACHE)) {
            return rest_ensure_response($cached);
        }

        set_time_limit(240); 
        
        // 1. CONFIGURATION: ISSN Overrides (Hardcode prices if missing)
        $issn_overrides = [
            '1234-5678' => 3000, 
        ];

        // 2. CONFIGURATION: Publisher Agreements
        $agreements = [
            'Taylor & Francis' => [
                'ids' => ['P4310320547', 'P4310319847'], // T&F + Routledge
                'start' => '2024-01-01'
            ],
            'Elsevier' => [
                'ids' => ['P4310320990'], 
                'start' => '2026-01-01', 
                'hybrid_only' => true
            ],
            'ACS' => [
                'ids' => ['P4310320006'], 
                'start' => '2025-01-01'
            ],
            'RSC' => [
                'ids' => ['P4310320556'], 
                'start' => '2024-01-01'
            ],
            'PLoS' => [
                'ids' => ['P4310315706'], 
                'start' => '2021-01-01', 
                'journals' => ['PLOS Biology', 'PLOS Medicine']
            ],
            'Wiley' => [
                'ids' => ['P4310320595'], 
                'start' => '2023-01-01'
            ],
            'Sage' => [
                'ids' => ['P4310320017'], 
                'start' => '2026-01-01', 
                'hybrid_only' => true
            ],
            'Springer' => [
                'ids' => ['P4310319965'], 
                'start' => '2025-01-01', 
                'exclude_nature' => true
            ],
            'Cambridge' => [
                'ids' => ['P4310311721'], 
                'start' => '2022-01-01'
            ],
            'ACM' => [
                'ids' => ['P4310319883'], 
                'start' => '2024-01-01'
            ],
            'ASM' => [
                'ids' => ['P4310319491'], 
                'start' => '2025-01-01', 
                'journals' => ['Antimicrobial Agents', 'Applied and Environmental', 'Infection and Immunity', 'Journal of Bacteriology', 'Journal of Clinical Microbiology', 'Journal of Virology']
            ],
            'Annual Reviews' => [
                'ids' => ['P4310320342'], 
                'start' => '2024-01-01', 
                'journal_regex' => '/(Anthropology|Biochemistry|Neuroscience|Political Science|Sociology)/i'
            ],
            'Cold Spring Harbor' => [
                'ids' => ['P4310315993'], 
                'start' => '2023-01-01'
            ],
            'Company of Biologists' => [
                'ids' => ['P4310315808'], 
                'start' => '2023-01-01'
            ],
            'MSP' => [
                'ids' => ['P4310316521'], 
                'start' => '2024-01-01', 
                'journals' => ['Geometry & Topology', 'Algebraic & Geometric Topology', 'Algebra & Number Theory', 'Analysis & PDE', 'Pacific Journal of Mathematics']
            ],
        ];

        // 3. FETCH DATA
        $base_url = 'https://api.openalex.org/works?filter=corresponding_institution_ids:' . self::BRANDEIS_ID . ',is_oa:true,type:article,publication_year:>2020&per-page=100&select=id,title,publication_date,publication_year,open_access,apc_list,primary_location,authorships';

        $all_results = [];
        $page = 1;
        $more_pages = true;
        
        while ($more_pages && $page <= 25) {
            $url = $base_url . "&page=" . $page;
            $response = $this->fetch_openalex($url);
            if (is_wp_error($response)) break;
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $results = $body['results'] ?? [];
            if (!empty($results)) {
                $all_results = array_merge($all_results, $results);
                $page++; 
                usleep(100000); 
            } else { 
                $more_pages = false; 
            }
        }

        $covered_articles = [];
        $publisher_stats = [];
        $total_savings = 0;

        foreach ($all_results as $work) {
            $source = $work['primary_location']['source'] ?? [];
            
            // --- HYBRID MATCHING: BUILD ID LIST ---
            // 1. Direct Host ID
            $check_ids = [];
            if (isset($source['host_organization'])) {
                $check_ids[] = $source['host_organization'];
            }
            // 2. Lineage IDs (Parent/Family)
            if (isset($source['host_organization_lineage'])) {
                $check_ids = array_merge($check_ids, $source['host_organization_lineage']);
            }
            
            // Clean IDs (remove URL prefix)
            $clean_ids = array_unique(array_map(function($id) {
                return str_replace('https://openalex.org/', '', $id);
            }, $check_ids));
            
            // --- MATCH PUBLISHER ---
            $matched_agreement = null;
            $agreement_name = '';

            foreach ($agreements as $name => $rules) {
                // Intersect to check if ANY of our agreement IDs are in the paper's ID list
                if (count(array_intersect($clean_ids, $rules['ids'])) > 0) {
                    $matched_agreement = $rules;
                    $agreement_name = $name;
                    break;
                }
            }
            if (!$matched_agreement) continue;

            // --- CHECK RULES ---
            $pub_date = $work['publication_date'];
            $journal_name = $source['display_name'] ?? '';
            $oa_status = $work['open_access']['oa_status'] ?? '';

            if ($pub_date < $matched_agreement['start']) continue;

            if (isset($matched_agreement['hybrid_only']) && $matched_agreement['hybrid_only'] && $oa_status !== 'hybrid') continue;

            if (isset($matched_agreement['journals'])) {
                $match = false;
                foreach($matched_agreement['journals'] as $j) {
                    if (stripos($journal_name, $j) !== false) { $match = true; break; }
                }
                if (!$match) continue;
            }

            if (isset($matched_agreement['journal_regex'])) {
                if (!preg_match($matched_agreement['journal_regex'], $journal_name)) continue;
            }

            if (isset($matched_agreement['exclude_nature']) && $matched_agreement['exclude_nature']) {
                if (stripos($journal_name, 'Nature') !== false) continue;
            }

            // --- GET COST ---
            $cost = 0;
            if (isset($work['apc_list']['value_usd']) && $work['apc_list']['value_usd'] > 0) {
                $cost = $work['apc_list']['value_usd'];
            } elseif (isset($work['apc_list']['value']) && $work['apc_list']['currency'] === 'USD') {
                 $cost = $work['apc_list']['value'];
            }

            // Check ISSN Override
            if ($cost <= 0) {
                $issn = $source['issn_l'] ?? '';
                if (!empty($issn) && isset($issn_overrides[$issn])) {
                    $cost = $issn_overrides[$issn];
                }
            }

            $total_savings += $cost;

            if (!isset($publisher_stats[$agreement_name])) {
                $publisher_stats[$agreement_name] = ['name' => $agreement_name, 'count' => 0, 'savings' => 0];
            }
            $publisher_stats[$agreement_name]['count']++;
            $publisher_stats[$agreement_name]['savings'] += $cost;

            $author_display = 'Brandeis Corresponding Author';
            if (!empty($work['authorships'])) {
                foreach($work['authorships'] as $auth) {
                    foreach($auth['institutions'] as $inst) {
                        if ($inst['id'] === self::BRANDEIS_ID && ($auth['is_corresponding'] ?? false)) {
                            $author_display = $auth['author']['display_name'] ?? 'Brandeis Author';
                            break 2;
                        }
                    }
                }
            }

            // Prepare display string for publisher (e.g. "Taylor & Francis (Routledge)")
            $display_publisher = $agreement_name;
            $pub_name = $work['primary_location']['source']['host_organization_name'] ?? '';
            if ($pub_name && stripos($pub_name, $agreement_name) === false) {
                $display_publisher .= " (" . $pub_name . ")";
            }

            $covered_articles[] = [
                'title' => $work['title'],
                'journal' => $journal_name,
                'date' => $pub_date,
                'publisher' => $display_publisher, 
                'cost' => $cost,
                'author' => $author_display,
                'link' => 'https://doi.org/' . str_replace('https://doi.org/', '', $work['id']) 
            ];
        }

        usort($publisher_stats, function($a, $b) { return $b['savings'] <=> $a['savings']; });
        usort($covered_articles, function($a, $b) { return strcmp($b['date'], $a['date']); });

        $data = [
            'total_avoided' => $total_savings,
            'publisher_stats' => array_values($publisher_stats),
            'articles' => $covered_articles,
            'article_count' => count($covered_articles)
        ];

        set_transient(self::OA_AVOIDANCE_CACHE, $data, self::CACHE_DURATION);
        return rest_ensure_response($data);
    }

    /**
     * --- CONTROLLER 7: LIBINSIGHT EVENTS ---
     */
    public function get_libinsight_data() {
        if (!defined('LIBINSIGHT_CLIENT_ID')) return new WP_Error('missing_config', 'LibInsight Config Missing');
        if ($cached = get_transient(self::LIBINSIGHT_CACHE)) return rest_ensure_response($cached);
        
        $host = "brandeis.libinsight.com";
        $gridId = LIBINSIGHT_GRID_ID; 

        $token_resp = wp_remote_post("https://$host/v1.0/oauth/token", ['body' => ['client_id' => LIBINSIGHT_CLIENT_ID, 'client_secret' => LIBINSIGHT_CLIENT_SECRET, 'grant_type' => 'client_credentials']]);
        if (is_wp_error($token_resp)) return rest_ensure_response(['error'=>true]);
        $token = json_decode(wp_remote_retrieve_body($token_resp), true)['access_token'] ?? null;
        if(!$token) return rest_ensure_response(['error'=>true]);

        $master_records = []; 
        $current_date = new DateTime('2017-07-01');
        $final_date   = new DateTime('last day of previous month'); 
        $guard = 0;

        while ($current_date < $final_date && $guard < 20) {
            $guard++;
            $chunk_start = $current_date->format('Y-m-d');
            $current_date->modify('+1 year');
            $chunk_end = ($current_date > $final_date) ? $final_date->format('Y-m-d') : $current_date->modify('-1 day')->format('Y-m-d');
            
            $page = 1; $more = true;
            while ($more) {
                $url = "https://$host/v1.0/custom-dataset/$gridId/data-grid?from=$chunk_start&to=$chunk_end&page=$page";
                $resp = wp_remote_get($url, ['headers' => ['Authorization' => "Bearer $token", 'Accept' => 'application/json'], 'timeout' => 20]);
                if (is_wp_error($resp)) break;
                $body = json_decode(wp_remote_retrieve_body($resp), true);
                if (!empty($body['payload']['records'])) $master_records = array_merge($master_records, $body['payload']['records']);
                if ($page >= ($body['payload']['total_pages'] ?? 1)) $more = false; else { $page++; usleep(200000); }
            }
            if($chunk_end == $final_date->format('Y-m-d')) break;
            $current_date->modify('+1 day');
        }

        $final = ['type' => 'success', 'payload' => ['records' => $master_records]];
        set_transient(self::LIBINSIGHT_CACHE, $final, self::CACHE_DURATION);
        return rest_ensure_response($final);
    }

   /**
     * --- CONTROLLER 8: LIBCAL EVENTS ---
     */
    public function get_libcal_data($request) {
        set_time_limit(120); 
        if (!defined('LIBCAL_CLIENT_ID')) return new WP_Error('missing_config', 'LibCal Config Missing');
        
        $token = $this->get_libcal_token();
        if (!$token) return rest_ensure_response(['error'=>true]);

        $year = isset($request['year']) ? intval($request['year']) : (int)date('Y');
        $cache_key = self::LIBCAL_DATA_CACHE_PREFIX . $year;
        if ($cached = get_transient($cache_key)) return rest_ensure_response($cached);

        $events = [];
        $page = 1; $more = true; $guard = 0;
        while($more && $guard < 20) {
            $guard++;
            $url = "https://calendar.library.brandeis.edu/api/1.1/events?cal_id=".self::LIBCAL_CALENDAR_ID."&date=$year-01-01&days=365&limit=500&page=$page";
            $resp = wp_remote_get($url, ['headers' => ['Authorization' => "Bearer $token"], 'timeout' => 20]);
            if (is_wp_error($resp)) break;
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            $new = $body['events'] ?? [];
            if (empty($new)) $more = false;
            else { $events = array_merge($events, $new); if(count($new) < 500) $more = false; else { $page++; usleep(100000); } }
        }

        $final = ['type' => 'success', 'year' => $year, 'payload' => ['records' => $events, 'count' => count($events)]];
        set_transient($cache_key, $final, self::CACHE_DURATION);
        return rest_ensure_response($final);
    }

    private function get_libcal_token() {
        if ($token = get_transient(self::LIBCAL_TOKEN_CACHE)) {
            return $token;
        }

        $host = "calendar.library.brandeis.edu";
        $response = wp_remote_post("https://$host/api/1.1/oauth/token", [
            'body' => [
                'client_id' => LIBCAL_CLIENT_ID,
                'client_secret' => LIBCAL_CLIENT_SECRET,
                'grant_type' => 'client_credentials'
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) return false;
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['access_token'])) {
            set_transient(self::LIBCAL_TOKEN_CACHE, $body['access_token'], 3500);
            return $body['access_token'];
        }

        return false;
    }

    private function fetch_openalex($url) {
        $email = 'repository@brandeis.edu'; 
        return wp_remote_get($url, [
            'headers' => ['User-Agent' => "BrandeisAnalytics/3.4 (mailto:$email)", 'Accept' => 'application/json'],
            'timeout' => 60
        ]);
    }

    private function process_collaborations($works) {
        $connections = [];
        foreach ($works as $work) {
            $authorships = $work['authorships'] ?? [];
            $totalAuthors = count($authorships);
            
            $paperInstitutions = [];
            foreach ($authorships as $author) {
                if (empty($author['institutions'])) continue;
                foreach ($author['institutions'] as $inst) {
                    $rawId = str_replace('https://openalex.org/', '', $inst['id']);
                    $consolidated = $this->consolidate_institution($rawId, $inst['display_name']);
                    $paperInstitutions[$consolidated['id']] = $consolidated['name'];
                }
            }
            if (isset($paperInstitutions[self::BRANDEIS_ID])) {
                foreach ($paperInstitutions as $id => $name) {
                    if ($id === self::BRANDEIS_ID) continue; 
                    $connections[] = [
                        'target_id' => $id,
                        'target_name' => $name,
                        'paper_title' => $work['title'],
                        'author_count' => $totalAuthors 
                    ];
                }
            }
        }
        return $connections;
    }

    private function consolidate_institution($id, $name) {
        $mappings = [
            'I2801851002' => ['id' => 'I136199984', 'name' => 'Harvard University'],
            'I403730302' => ['id' => 'I136199984', 'name' => 'Harvard University'],
            'I4210156908' => ['id' => 'I136199984', 'name' => 'Harvard University'], 
            'I4210154866' => ['id' => 'I136199984', 'name' => 'Harvard University'],
            'I2800683617' => ['id' => 'I136199984', 'name' => 'Harvard University'],
            'I4210156096' => ['id' => 'I887064364', 'name' => 'Tufts University'],
            'I4210129845' => ['id' => 'I887064364', 'name' => 'Tufts University'],
            'I4210103739' => ['id' => 'I11606837', 'name' => 'Boston University'],
            'I4210115049' => ['id' => 'I204250577', 'name' => 'Columbia University'],
            'I4210148419' => ['id' => 'I205783295', 'name' => 'Cornell University'],
        ];
        return $mappings[$id] ?? ['id' => $id, 'name' => $name];
    }
}

new Brandeis_Analytics_API();
