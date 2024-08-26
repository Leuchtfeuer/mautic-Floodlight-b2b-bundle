from whois import whois
from ipwhois import IPWhois

import argparse

def is_isp(organization_name, domain_name):
    isp_keywords = ["isp", "internet service provider", "tele", "telecom", "telecommunication", "telnet", "mobile", "wireless"]
    organization_name = "" if organization_name is None else organization_name
    domain_name = "" if domain_name is None else domain_name
    return any(keyword in organization_name.lower() or keyword in domain_name.lower() for keyword in isp_keywords)

def is_dialup(ip_address):
    mobile_carrier_ip_ranges = ["192.0.2.0/24", "2001:db8::/32"]
    return any(ip_address in ip_network for ip_network in mobile_carrier_ip_ranges)

def get_whois_info(ip_address):
    try:
        result = whois(ip_address)
        ip_result = IPWhois(ip_address).lookup_whois()
        
        domain = result.get('domain_name', '')
        domain = domain[0] if isinstance(domain, list) else domain
        
        ip_org = ip_result['nets'][0]['description'] if ip_result['nets'][0]['description'] is not None else ""
        ip_domain = ip_result['nets'][0]['emails'][0].split('@')[1] if ip_result['nets'][0]['emails'] is not None else ""
        
        organization_name = result.get('org', '') if result.get('org', '') is not None else ip_org
        domain_name = domain if domain is not None else ip_domain

        # Check if it's an ISP
        is_an_isp = is_isp(organization_name, domain_name)

        # Check if it's a dial-up or cell phone address
        is_dialup_address = is_dialup(ip_address)

        # Construct JSON response
        response_json = {
            "company_domain": domain_name,
            "company_name": organization_name,
            "is_isp": is_an_isp,
            "is_dialup": is_dialup_address
        }
        print(response_json)
        return response_json
    except Exception as e:
        print(f"Error: {e}")
        return None

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("-ipaddr", type=str)
    args = parser.parse_args()
    get_whois_info(args.ipaddr)